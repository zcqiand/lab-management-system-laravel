<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\InvalidCredentials;
use App\Exceptions\InvalidGrant;
use App\Exceptions\MenusUnavailable;
use App\Exceptions\SaasIdpUnavailable;
use App\Exceptions\UnauthorizedClient;
use App\Exceptions\UpstreamUnavailable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

// 认证域服务（rails app/services/auth/auth_service.rb 逐语义移植，lab-springboot
// AuthService 同源 ADR-0008）：
// - JWT：JwtService 出真签名（HS256，lab 形状无 aud）
// - SSO：SaasAuthClient 真调 saas /oauth/token；SaasMeClient 拿 /me 快照
// - CSRF：state 由前端生成，后端透传（RFC 6749 §10.12），后端不校验
// - refresh：lab refresh token 内嵌 saas refresh token（rotate-once）
// 菜单/租户快照：进程内 30min TTL（SnapshotCache），SSO/refresh/密码登录时点填充。
//
// mock seam（rails define_singleton_method 同位）：进程内单例 instance()/reset()；
// 协作者公有属性，测试可整件替换（匿名类 extends 真 client，构造不跑、永不真打 HTTP）。
final class AuthService
{
    // msw 权限集（admin 全量 11 项，handlers-extra.ts:160-175 镜像）
    public const DEMO_PERMISSIONS = [
        'contract:read', 'contract:write', 'sample:read', 'sample:write', 'report:read',
        'report:write', 'report:issue', 'inspection:read', 'inspection:write', 'audit:read', '*',
    ];

    // lab 家族在 saas 注册的 appCode（seeds apps.json）
    public const LAB_APP_CODE = 'lab-management';

    public UserDirectory $directory;

    public SaasAuthClient $saasAuth;

    public SaasMeClient $saasMe;

    public JwtService $tokenService;

    public SnapshotCache $menuCache;

    public SnapshotCache $membershipCache;

    public SaasMenuMapper $menuMapper;

    private static ?self $instance = null;

    private function __construct()
    {
        $this->directory = new UserDirectory;
        $this->saasAuth = new SaasAuthClient;
        $this->saasMe = new SaasMeClient;
        $this->tokenService = new JwtService;
        $this->menuCache = new SnapshotCache;
        $this->membershipCache = new SnapshotCache;
        $this->menuMapper = new SaasMenuMapper;
    }

    // 进程内单例（目录与快照缓存跨请求存活；test 用 ::reset() 隔离）
    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    // === 密码登录（M01.F05.I01；native-login 同口径） ===

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function login(array $body): array
    {
        $username = trim(self::str($body['username'] ?? null));
        $password = self::str($body['password'] ?? null);
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('username and password are required');
        }

        // REQ-2026-023（2026-10-11 人裁方案 A）：账密校验代理 saas IdP——请求体账密调
        // saas /api/v1/auth/login（clientId=LAB_SAAS_CLIENT_ID）。错误映射五仓统一：
        // saas 4xx → 401 INVALID_CREDENTIALS（不透传）；5xx/不可达 → 502 SAAS_UNAVAILABLE。
        $saasLogin = $this->proxySaasLogin($username, $password);
        $saasUser = is_array($saasLogin['user'] ?? null) ? $saasLogin['user'] : [];
        $tenants = $this->loginTenants($saasLogin, $saasUser);
        $user = $this->loginUser($saasLogin, $saasUser, $tenants);
        // 密码登录用户无 per-user saas refresh token → 仍用服务账号拉菜单快照
        // （REQ-2026-023 非目标，行为不动：失败不阻塞登录，miss 时 menus 503）
        $this->cacheMenusWithServiceAccount($user['id']);
        $this->membershipCache->put($user['id'], $tenants);
        $this->upsertLoginDirectory($saasUser, $user);
        // lab 自家 JWT 重签（tenant_id=saas currentTenantId ?? 首租户）；
        // refresh 维持 dev-placeholder（session 第 4 参 null，不透传 saas refreshToken）
        $currentTenantId = self::presence($saasLogin['currentTenantId'] ?? null)
            ?? ($tenants[0]['tenant_id'] ?? null);

        return $this->session($user, $currentTenantId, $tenants);
    }

    // === 刷新 token（M01.F05.I04） ===

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function refresh(array $body): array
    {
        $token = self::str($body['refreshToken'] ?? null);
        if ($token === '') {
            throw new InvalidCredentials('missing refresh_token');
        }

        try {
            $claims = $this->tokenService->verify($token, JwtService::TYP_REFRESH);
        } catch (Throwable $e) { // 基类含 TokenExpired 与 typ/iss 校验错
            throw new InvalidCredentials('invalid refresh_token: '.$e->getMessage());
        }

        $saasRefresh = self::str($claims['saas_refresh_token'] ?? null);
        if ($saasRefresh === '') {
            throw new InvalidCredentials('invalid refresh_token: missing saas_refresh_token claim');
        }

        try {
            $t = $this->saasAuth->token('refresh_token', refreshToken: $saasRefresh);

            return $this->hydrateAndSession(self::str($claims['tenant_id'] ?? null), $t);
        } catch (InvalidGrant|UnauthorizedClient $e) {
            throw new InvalidCredentials('saas refresh failed: '.$e->getMessage());
        }
    }

    // === 登出（无状态 JWT，服务端无 session store） ===

    /** @param array<string, mixed> $body */
    public function logout(array $body): void {}

    // === 当前会话（M00.F01.I01） ===

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    public function me(array $claims): array
    {
        $user = $this->resolveUser($claims);
        // REQ-2026-023 live 回归：快照 hit 优先（SSO 与密码登录用户同链——密码登录
        // 也在 login 时点填快照）；miss 且持 saas refresh（SSO 用户）→ refresh required；
        // miss 且无 refresh（密码登录用户）→ demo 目录降级。
        $cached = $this->membershipCache->get($user['id']);
        // 空快照（[]，非 null）也是 hit：家族裁定（I2 双栈 4893341/2437aa7，tracker 5.111）
        // 空即空 → tenants=[] currentTenantId=''；不得误判 miss 走 refresh-required/demo 降级
        if ($cached !== null) {
            $cachedTenants = is_array($cached) ? $cached : [];
            $current = self::presence(self::str($claims['tenant_id'] ?? null))
                ?? ($cachedTenants[0]['tenant_id'] ?? '');

            return [
                'user' => $this->userStruct($user),
                'tenants' => $cachedTenants,
                'current_tenant_id' => $current,
            ];
        }

        $saasRefresh = $this->directory->getSaasRefreshToken($user['id']);
        if (is_string($saasRefresh) && $saasRefresh !== '') {
            throw new InvalidCredentials(
                "membership snapshot unavailable for user {$user['id']} (cache miss); refresh required"
            );
        }

        $current = self::presence(self::str($claims['tenant_id'] ?? null))
            ?? $this->directory->defaultTenant()['tenant_id'];

        return [
            'user' => $this->userStruct($user),
            'tenants' => $this->directoryTenants($user),
            'current_tenant_id' => $current,
        ];
    }

    // === 选租户换发（M00.F02.I01） ===

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function switchTenant(array $claims, array $body): array
    {
        $user = $this->resolveUser($claims);
        $tenantId = self::str($body['tenantId'] ?? null);
        // REQ-2026-023 live 回归：归属=用户当前会话的租户集（membership 快照，
        // login/sso/refresh 写入），demo 目录只作快照 miss 兜底——写死 demo 字面量
        // 会把 saas UUID 租户 404。命中换发（tenant_id=目标租户），响应租户同源。
        $cached = $this->membershipCache->get($user['id']);
        $knownTenants = is_array($cached) && $cached !== [] ? $cached : $this->directoryTenants($user);
        $target = null;
        foreach ($knownTenants as $t) {
            if (($t['tenant_id'] ?? null) === $tenantId) {
                $target = $t;
                break;
            }
        }
        if ($target === null) {
            // rails ActiveRecord::RecordNotFound 'Tenant not found' 镜像 → 基座
            // NotFoundHttpException(previous=ModelNotFound) 映射 404 NOT_FOUND。
            throw new NotFoundHttpException('Tenant not found', new ModelNotFoundException);
        }

        return $this->session($user, $target['tenant_id'], $knownTenants);
    }

    // === 动态菜单 / 权限集（M01.F04.I01/I02） ===

    // miss（快照过期/密码登录未填充/重启）抛 MenusUnavailable → 503；
    // demo 兜底已删（2026-08-27），前端 useBackendMenus 失败回退静态菜单。
    /**
     * @param  array<string, mixed>  $claims
     * @return list<array<string, mixed>>
     */
    public function menus(array $claims): array
    {
        $sub = self::str($claims['sub'] ?? null);
        $snapshot = $this->menuCache->get($sub);
        if (! is_array($snapshot) || $snapshot === []) {
            throw new MenusUnavailable("menu snapshot unavailable for user {$sub}; re-login to refresh");
        }

        return $snapshot;
    }

    /** @return array<string, list<string>> */
    public function permissions(): array
    {
        return ['permissions' => self::DEMO_PERMISSIONS];
    }

    // === SSO 跳转（M01.F05.I02） ===

    // 302 跳板语义（2026-08-29 收敛）：直接回 saas 登录页 URL（带 redirect_uri+state+client_id），
    // 调用方 redirect_uri 缺失 fail-fast（ADR-0019，2026-09-23 跨前端事故同款）。
    /** @return array<string, string> */
    public function ssoAuthorize(?string $businessRedirect, ?string $frontendState): array
    {
        if (self::presence($businessRedirect) === null) {
            throw new InvalidArgumentException('missing redirect_uri');
        }

        $state = self::str($frontendState);
        $loginUrl = Env::get('LAB_SSO_LOGIN_URL');
        if (! is_string($loginUrl) || $loginUrl === '') {
            throw new RuntimeException('LAB_SSO_LOGIN_URL 缺失（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }
        $clientId = Env::get('LAB_SAAS_CLIENT_ID');
        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('LAB_SAAS_CLIENT_ID 缺失（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }

        $authorizeUrl = rtrim($loginUrl, '/').'/login'
            .'?redirect_uri='.rawurlencode($businessRedirect)
            .'&state='.rawurlencode($state)
            .'&client_id='.rawurlencode($clientId);

        return ['authorizeUrl' => $authorizeUrl, 'state' => $state];
    }

    // === SSO 回调（M01.F05.I03） ===

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function ssoCallback(array $body): array
    {
        if ($body === []) {
            throw new InvalidArgumentException('missing body');
        }

        try {
            $t = $this->saasAuth->token(
                'authorization_code',
                code: self::str($body['code'] ?? null),
                redirectUri: self::presence($body['redirectUri'] ?? null)
            );

            return $this->hydrateAndSession(null, $t, callback: true);
        } catch (InvalidGrant $e) {
            throw $e; // 400 INVALID_GRANT（code 重放/过期，对齐 lab-msw 契约）
        } catch (UnauthorizedClient $e) {
            throw new InvalidCredentials($e->getMessage());
        }
    }

    // === private ===

    // 账密代理出站（REQ-2026-023）。错误归类：saas 4xx → 401（不透传 saas 内部码）；
    // 5xx/连接失败 → SaasIdpUnavailable（基座映射 502 SAAS_UNAVAILABLE）
    /** @return array<string, mixed> */
    private function proxySaasLogin(string $username, string $password): array
    {
        try {
            return $this->saasAuth->userLogin($username, $password);
        } catch (InvalidGrant|UnauthorizedClient) {
            throw new InvalidCredentials('Invalid username or password');
        } catch (UpstreamUnavailable $e) {
            throw new SaasIdpUnavailable('saas IdP unavailable: '.$e->getMessage());
        }
    }

    // 代理登录响应 → 租户列表：saas user 无 displayName、availableTenants 无租户名 →
    // 瞬时持 accessToken 拉平台租户列表富化（best-effort，失败降级 name=tenantId）
    /**
     * @param  array<string, mixed>  $saasLogin
     * @param  array<string, mixed>  $saasUser
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    private function loginTenants(array $saasLogin, array $saasUser): array
    {
        $available = $saasLogin['availableTenants'] ?? [];
        $userId = self::presence($saasLogin['userId'] ?? null) ?? self::str($saasUser['id'] ?? null);

        return $this->tenantsFromLogin(
            is_array($available) ? $available : [],
            $this->fetchTenantNames($userId !== '' ? $userId : null, self::presence($saasLogin['accessToken'] ?? null))
        );
    }

    // REQ-2026-023 live 回归（2026-10-11 五目标 401 指纹）：密码登录用户落
    // directory（镜像 upsert_lab_user 链）——me/switch_tenant 的 resolve_user
    // 按 sub=saas user id 解析，不落目录必 unknown user 401。email 缺失降级
    // username 作 upsert 键（ADR-0008 同款 email 桥接约定）。
    /**
     * @param  array<string, mixed>  $saasUser
     * @param  array{id: string, username: string, display_name: string, role_code: string}  $user
     */
    private function upsertLoginDirectory(array $saasUser, array $user): void
    {
        $this->directory->upsert(
            $user['id'],
            self::presence(self::str($saasUser['email'] ?? null)) ?? $user['username'],
            $user['display_name'],
            $user['role_code']
        );
    }

    // saas user → lab CurrentUser 直构（目录落库在 login 主体做，upsert 键同链）；
    // id/username/displayName 三段 presence 回退链（presence 空串 falsy 语义——
    // saas 无 displayName 时为空串，家族记忆 saas-no-displayname-empty-string-fallback-trap）
    /**
     * @param  array<string, mixed>  $saasLogin
     * @param  array<string, mixed>  $saasUser
     * @param  list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>  $tenants
     * @return array{id: string, username: string, display_name: string, role_code: string}
     */
    private function loginUser(array $saasLogin, array $saasUser, array $tenants): array
    {
        $firstRoleId = $tenants[0]['role_ids'][0] ?? null;

        return [
            'id' => self::presence(self::str($saasUser['id'] ?? null))
                ?? self::presence(self::str($saasLogin['userId'] ?? null)) ?? 'unknown',
            'username' => self::presence(self::str($saasUser['username'] ?? null))
                ?? self::presence(self::str($saasUser['email'] ?? null)) ?? 'unknown',
            'display_name' => self::presence(self::str($saasUser['displayName'] ?? null))
                ?? self::presence(self::str($saasUser['username'] ?? null))
                ?? self::presence(self::str($saasUser['email'] ?? null)) ?? '未知用户',
            'role_code' => self::presence(is_string($firstRoleId) ? $firstRoleId : null) ?? 'member',
        ];
    }

    // availableTenants → MyTenant 形状（REQ-2026-023）：过滤 removed/无 tenantId 后
    // 复用 memberships 同款映射（富化 miss 降级 code=name=tenantId，与 tenantsFrom 同语义）
    /**
     * @param  list<array<string, mixed>>  $available
     * @param  array<string, array<string, mixed>>  $nameById
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    private function tenantsFromLogin(array $available, array $nameById): array
    {
        $active = [];
        foreach ($available as $t) {
            $tenantId = self::presence(self::str($t['tenantId'] ?? null));
            if ($tenantId === null || ($t['status'] ?? null) === 'removed') {
                continue;
            }
            $active[] = $t;
        }

        return $this->tenantsFrom($active, $nameById);
    }

    // saas token 到手后的公共水合：whoami → upsert 目录 → 菜单快照 → refresh token
    // 存回（rotate-once）→ 平台租户名富化 → memberships 快照 → 签发会话。
    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function hydrateAndSession(?string $tenantId, array $t, bool $callback = false): array
    {
        try {
            $saasUser = $this->saasMe->whoami(self::str($t['accessToken'] ?? null));
            $labUser = $this->upsertLabUser($saasUser);
            $this->cacheMenus($labUser['id'], self::presence($t['accessToken'] ?? null));
            $this->directory->setSaasRefreshToken($labUser['id'], self::str($t['refreshToken'] ?? null));
            $tenants = $this->snapshotMemberships($labUser['id'], self::presence($t['accessToken'] ?? null));

            $sessionTenantId = $callback
                ? self::str($saasUser['currentTenantId'] ?? null)
                : $tenantId;

            return $this->session($labUser, $sessionTenantId, $tenants, self::presence($t['refreshToken'] ?? null));
        } catch (UpstreamUnavailable $e) {
            throw new UpstreamUnavailable($e->getMessage());
        }
    }

    // saas email 桥接目录 upsert（ADR-0008）：命中即复用，未命中以 viewer 落地
    /**
     * @param  array<string, mixed>  $saasUser
     * @return array{id: string, username: string, display_name: string, role_code: string}
     */
    private function upsertLabUser(array $saasUser): array
    {
        $email = self::str($saasUser['email'] ?? null);
        $hit = $this->directory->findByEmail($email);
        if ($hit !== null) {
            return $hit;
        }

        return $this->directory->upsert(
            self::str($saasUser['id'] ?? null),
            $email,
            self::str($saasUser['displayName'] ?? null),
            'viewer'
        );
    }

    // memberships 快照 + 平台租户名富化，进缓存（me() 的 SSO 租户体系数据源）
    /**
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    private function snapshotMemberships(string $userId, ?string $saasAccessToken): array
    {
        $nameById = $this->fetchTenantNames($userId, $saasAccessToken);
        $tenants = $this->tenantsFrom($this->saasMe->listMyTenants(self::str($saasAccessToken)), $nameById);
        $this->membershipCache->put($userId, $tenants);

        return $tenants;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array{id: string, username: string, display_name: string, role_code: string}
     */
    private function resolveUser(array $claims): array
    {
        $sub = self::str($claims['sub'] ?? null);
        if ($sub === '') {
            throw new InvalidCredentials('missing sub claim');
        }

        $hit = $this->directory->findById($sub)
            ?? $this->directory->findByEmail($sub)
            ?? $this->directory->findByUsername($sub);
        if ($hit === null) {
            throw new InvalidCredentials("unknown user: {$sub}");
        }

        return $hit;
    }

    /**
     * @param  array{id: string, username: string, display_name: string, role_code: string}  $user
     * @param  list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>|null  $tenants
     * @return array<string, mixed>
     */
    private function session(array $user, ?string $tenantId, ?array $tenants, ?string $saasRefreshToken = null): array
    {
        $accessToken = $this->tokenService->issueAccess($user['id'], $tenantId);
        $refreshToken = $this->tokenService->issueRefresh($user['id'], $saasRefreshToken ?? 'dev-placeholder');
        $useTenants = $tenants ?? $this->directoryTenants($user);

        return [
            'token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => $this->userStruct($user),
            'tenants' => $useTenants,
        ];
    }

    /**
     * @param  array{id: string, username: string, display_name: string, role_code: string}  $user
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    private function directoryTenants(array $user): array
    {
        return $this->directory->tenantsOf($user['username']);
    }

    // memberships → MyTenant 形状；名字富化 miss 降级 code=name=tenantId（切租户器不空）
    /**
     * @param  list<array<string, mixed>>  $memberships
     * @param  array<string, array<string, mixed>>  $nameById
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    private function tenantsFrom(array $memberships, array $nameById): array
    {
        $tenants = [];
        foreach ($memberships as $m) {
            $tenantId = self::str($m['tenantId'] ?? null);
            $t = $nameById[$tenantId] ?? [];
            $roleIds = $m['roleIds'] ?? [];
            $tenants[] = [
                'tenant_id' => $tenantId,
                'code' => self::presence(self::str($t['tenantKey'] ?? null)) ?? $tenantId,
                'name' => self::presence(self::str($t['name'] ?? null)) ?? $tenantId,
                'role_ids' => is_array($roleIds) ? array_values($roleIds) : [],
            ];
        }

        return $tenants;
    }

    // 失败只 warn 返空（best-effort，不阻塞登录；名字降级 tenantId）
    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchTenantNames(?string $userId, ?string $saasAccessToken): array
    {
        if ($userId === null || $userId === '' || $saasAccessToken === null || $saasAccessToken === '') {
            return [];
        }

        try {
            $nameById = [];
            foreach ($this->saasMe->listPlatformTenants($saasAccessToken) as $t) {
                if (is_array($t)) {
                    $id = self::str($t['id'] ?? null);
                    if ($id !== '') {
                        $nameById[$id] = $t;
                    }
                }
            }

            return $nameById;
        } catch (Throwable $e) {
            Log::warning("tenant name lookup failed for user {$userId}: {$e->getMessage()}");

            return [];
        }
    }

    // SSO/refresh 时点唯一持有 saas accessToken 的窗口，顺手拉菜单快照；失败不阻塞
    private function cacheMenus(string $userId, ?string $saasAccessToken): void
    {
        if ($userId === '' || $saasAccessToken === null || $saasAccessToken === '') {
            return;
        }

        try {
            $snapshot = $this->saasMe->listMyMenus($saasAccessToken, self::LAB_APP_CODE);
            $this->menuCache->put($userId, $this->menuMapper->map($snapshot));
        } catch (Throwable $e) {
            Log::warning("menu snapshot fetch failed for user {$userId}: {$e->getMessage()}");
        }
    }

    // 密码登录路径：用服务账号登 saas 换 token 再拉菜单；失败只 warn
    private function cacheMenusWithServiceAccount(string $userId): void
    {
        if ($userId === '') {
            return;
        }

        try {
            $serviceUser = Env::get('LAB_SAAS_SERVICE_USER');
            $servicePassword = Env::get('LAB_SAAS_SERVICE_PASSWORD');
            if (! is_string($serviceUser) || $serviceUser === ''
                || ! is_string($servicePassword) || $servicePassword === '') {
                throw new RuntimeException('LAB_SAAS_SERVICE_USER/PASSWORD 缺失');
            }
            $t = $this->saasAuth->serviceLogin($serviceUser, $servicePassword);
            $this->cacheMenus($userId, self::presence($t['accessToken'] ?? null));
        } catch (Throwable $e) {
            Log::warning("service-account menu snapshot failed for user {$userId}: {$e->getMessage()}");
        }
    }

    /**
     * @param  array{id: string, username: string, display_name: string, role_code: string}  $user
     * @return array<string, mixed>
     */
    private function userStruct(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role_code' => $user['role_code'],
        ];
    }

    // rails String#presence 语义镜像：非空串原样，其余 null（'0' 算 present）
    private static function presence(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
