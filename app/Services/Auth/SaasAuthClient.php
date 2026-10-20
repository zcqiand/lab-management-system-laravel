<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\InvalidGrant;
use App\Exceptions\UnauthorizedClient;
use App\Exceptions\UpstreamUnavailable;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// saas IdP 真对接（lab-springboot SaasAuthClient 镜像，rails app/services/auth/
// saas_auth_client.rb 逐语义移植，Net::HTTP → Laravel HTTP 客户端）。
// - serviceLogin：服务账号密码登 saas /api/v1/auth/login（密码登录用户拉菜单快照用）
// - userLogin：原生登录账密校验代理（REQ-2026-023；clientId 取 OAuth client_id）
// - token：OAuth §4.1.3/§6 code 换 token 或 refresh_token 续（/api/v1/oauth/token）
// 错误映射：401→UnauthorizedClient / 4xx→InvalidGrant / 5xx+网络→UpstreamUnavailable。
//
// 非 final（测试桩匿名类 extends 本类整件替换——真实构造不跑，永不真打 HTTP）。
// base url 类 env 全 fail-fast 读取（suite-hard-rules §1，禁默认值兜底）。
class SaasAuthClient
{
    private string $saasBase;

    private string $clientId;

    private string $clientSecret;

    private string $defaultTenantId;

    private string $serviceClientId;

    public function __construct()
    {
        $this->saasBase = rtrim(self::requireEnv('LAB_SAAS_BASE_URL'), '/');
        $this->clientId = self::requireEnv('LAB_SAAS_CLIENT_ID');
        $this->clientSecret = self::requireEnv('LAB_SAAS_CLIENT_SECRET');
        // oauth token 请求体 tenantId 形参（saas 服务账号域 GUID；rails 同款消费——
        // 禁用面只在 lab 业务租户回退，JwtGuard/UserDirectory 侧必须硬编码 TENANT-001）。
        $this->defaultTenantId = self::requireEnv('LAB_SAAS_DEFAULT_TENANT_ID');
        $this->serviceClientId = self::requireEnv('LAB_SAAS_SERVICE_CLIENT_ID');
    }

    // body 对齐 saas LoginRequest 契约 {username, password, clientId}（2026-09-19 5.33 同款）。
    // @return array{accessToken:?string, refreshToken:?string, tokenType:?string, expiresIn:?int, scope:?string}
    public function serviceLogin(string $username, string $password): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
            'clientId' => $this->serviceClientId,
        ]);
    }

    // REQ-2026-023：原生登录账密校验代理——saas /api/v1/auth/login 完整响应
    // {user, availableTenants, accessToken, refreshToken, userId, currentTenantId}。
    // 注意 saas user 无 displayName、availableTenants 无租户名（富化走 /admin/tenants）。
    //
    // @return array{user:?array<string,mixed>, availableTenants:list<array<string,mixed>>, accessToken:?string, refreshToken:?string, userId:?string, currentTenantId:?string}
    public function userLogin(string $username, string $password): array
    {
        return $this->postJson('/api/v1/auth/login', [
            'username' => $username,
            'password' => $password,
            'clientId' => $this->clientId,
        ]);
    }

    // grantType=authorization_code（code+redirectUri）或 refresh_token（refreshToken）。
    //
    // @return array<string, mixed>
    public function token(string $grantType, ?string $code = null, ?string $refreshToken = null, ?string $redirectUri = null): array
    {
        $body = ['grantType' => $grantType, 'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret, 'tenantId' => $this->defaultTenantId];
        if (is_string($code) && $code !== '') {
            $body['code'] = $code;
        }
        if (is_string($refreshToken) && $refreshToken !== '') {
            $body['refreshToken'] = $refreshToken;
        }
        if (is_string($redirectUri) && $redirectUri !== '') {
            $body['redirectUri'] = $redirectUri;
        }

        return $this->postJson('/api/v1/oauth/token', $body);
    }

    // @return array<string, mixed>
    private function postJson(string $path, array $body): array
    {
        try {
            $res = Http::timeout(10)->post($this->saasBase.$path, $body);
        } catch (ConnectionException|TransferException $e) {
            throw new UpstreamUnavailable('saas connect failed: '.$e->getMessage());
        }

        if ($res->successful()) {
            /** @var array<string, mixed> */
            return (array) $res->json();
        }

        $status = $res->status();
        if ($status === 401) {
            throw new UnauthorizedClient('saas 401 unauthorized_client');
        }
        if ($status < 500) {
            throw new InvalidGrant('saas '.$status.' '.$this->truncate((string) $res->body()));
        }

        throw new UpstreamUnavailable('saas upstream 5xx: '.$status);
    }

    private function truncate(string $s, int $max = 200): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max).'...';
    }

    private static function requireEnv(string $key): string
    {
        $value = Env::get($key);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException($key.' 缺失（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }

        return $value;
    }
}
