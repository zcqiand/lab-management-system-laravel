<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\InvalidGrant;
use App\Exceptions\UpstreamUnavailable;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FakeSaasAuth;
use Tests\Support\FakeSaasMe;
use Tests\TestCase;

// M00.F01/F02 + M01.F04/F05 认证域集成测试（镜像 lab rails test/integration/
// auth_flow_test.rb 全 24 用例 + lab-ct auth*.test.ts 断言口径）。saas 上游用
// FakeSaasAuth/FakeSaasMe 注入桩（extends 真 client、构造不跑）——测试永不真打
// HTTP（连 localhost 也不打）；真链路对拍归 lab-ct live。
final class AuthFlowTest extends TestCase
{
    private AuthService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        AuthService::reset();
        $this->svc = AuthService::instance();
        $this->svc->saasAuth = new FakeSaasAuth;
        $this->svc->saasMe = new FakeSaasMe;
    }

    protected function tearDown(): void
    {
        AuthService::reset();
        parent::tearDown();
    }

    // ==== 密码登录（login / native-login） ====

    #[Test]
    public function m00_f01_i02_login_success_returns_token_pair_demo_user_and_3_tenants(): void
    {
        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'dev123456']);

        $response->assertOk();
        $body = $response->json();
        $this->assertNotEmpty($body['token']);
        $this->assertNotEmpty($body['refreshToken']);
        $this->assertSame('USER-A', $body['user']['id']);
        $this->assertSame('alice', $body['user']['username']);
        $this->assertCount(3, $body['tenants']);
        $this->assertSame('TENANT-001', $body['tenants'][0]['tenantId']);
    }

    #[Test]
    public function login_wrong_password_is_401_invalid_credentials(): void
    {
        // REQ-2026-023：校验代理 saas IdP —— saas 4xx（口令错）→ 401（不透传）
        $this->svc->saasAuth = new FakeSaasAuth(
            userLoginFn: fn (): array => throw new InvalidGrant('saas 400 wrong password')
        );

        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'nope']);

        $response->assertUnauthorized();
        $this->assertSame('INVALID_CREDENTIALS', $response->json('code'));
    }

    #[Test]
    public function login_missing_username_is_400(): void
    {
        $response = $this->postJson('/api/auth/login', ['username' => '', 'password' => 'x']);

        $response->assertBadRequest();
    }

    #[Test]
    public function native_login_shares_the_same_credential_path(): void
    {
        $response = $this->postJson('/api/auth/native-login', ['username' => 'alice', 'password' => 'dev123456']);

        $response->assertOk();
        $this->assertSame('USER-A', $response->json('user.id'));
    }

    // ==== REQ-2026-023：登录账密校验代理 saas IdP（五仓统一语义锁） ====

    #[Test]
    public function m01_f05_i01_login_proxies_saas_idp_and_maps_saas_identity_with_lab_jwt_resign(): void
    {
        // 平台租户只富化 ten-saas-1（ten-saas-2 不在列表 → 降级 tenantId）
        $this->svc->saasMe = new FakeSaasMe(
            listPlatformTenantsFn: fn (): array => [['id' => 'ten-saas-1', 'name' => '市检测中心', 'tenantKey' => 'city']]
        );
        // stub_proxy_login：记录出站账密；saas user 无 displayName、双租户
        // （ten-saas-2 供降级路径断言）；stub 忽略账密——出站只透传原值
        $proxy = new FakeSaasAuth(userLoginFn: fn (): array => [
            'user' => ['id' => 'saas-u-alice', 'username' => 'alice', 'email' => 'alice@saas.local'],
            'availableTenants' => [
                ['tenantId' => 'ten-saas-1', 'roleIds' => ['admin'], 'status' => 'active'],
                ['tenantId' => 'ten-saas-2', 'roleIds' => ['technician'], 'status' => 'active'],
            ],
            'accessToken' => 'u-at', 'refreshToken' => 'u-rt',
            'userId' => 'saas-u-alice', 'currentTenantId' => null,
        ]);
        $this->svc->saasAuth = $proxy;

        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'pw-123']);

        $response->assertOk();
        $body = $response->json();
        // 出站透传：账密原值进 user_login
        $this->assertSame('alice', $proxy->userLoginCalls[0]['username']);
        $this->assertSame('pw-123', $proxy->userLoginCalls[0]['password']);
        // user 映射自 saas；roleCode=首租户 roleIds[0]
        $this->assertSame('saas-u-alice', $body['user']['id']);
        $this->assertSame('alice', $body['user']['username']);
        $this->assertSame('admin', $body['user']['roleCode']);
        // tenants 四字段 + 富化真名/tenantKey；miss 降级 tenantId
        $this->assertCount(2, $body['tenants']);
        $this->assertSame('ten-saas-1', $body['tenants'][0]['tenantId']);
        $this->assertSame('city', $body['tenants'][0]['code']);
        $this->assertSame('市检测中心', $body['tenants'][0]['name']);
        $this->assertSame('ten-saas-2', $body['tenants'][1]['code']);
        $this->assertSame('ten-saas-2', $body['tenants'][1]['name']);
        // lab 自家 JWT 重签：可验签，sub=saas id，tenant_id=首租户；
        // refresh 维持 dev-placeholder（不透传 saas refreshToken）
        $claims = (new JwtService)->verify($body['token']);
        $this->assertSame('saas-u-alice', $claims['sub']);
        $this->assertSame('ten-saas-1', $claims['tenant_id']);
        $refreshClaims = (new JwtService)->verify($body['refreshToken'], JwtService::TYP_REFRESH);
        $this->assertSame('dev-placeholder', $refreshClaims['saas_refresh_token']);
    }

    #[Test]
    public function m01_f05_i01_login_saas_unavailable_is_502_saas_unavailable(): void
    {
        $this->svc->saasAuth = new FakeSaasAuth(
            userLoginFn: fn (): array => throw new UpstreamUnavailable('saas connect failed')
        );

        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'pw']);

        $response->assertStatus(502);
        $this->assertSame('SAAS_UNAVAILABLE', $response->json('code'));
    }

    // ==== REQ-2026-023 live 回归（2026-10-11 五目标同时失败指纹） ====
    // 契约：登录后 me/switch 必须与登录响应同一租户体系（2026-09-03 对齐约束：
    // tenants.find(activeTenantId) 必命中）。

    #[Test]
    public function m00_f01_i01_m00_f02_i01_login_session_coherence_me_and_switch_stay_in_saas_tenant_system(): void
    {
        // live 回归替身：saas 真实租户是 UUID 形（live 实测），刻意不用 TENANT-00x
        // 字面量——会与 demo 目录撞键掩盖失配（本测试的存在理由）。
        $uuidUser = '00000000-0000-0000-0000-b00000000001';
        $uuidTenant1 = '00000000-0000-0000-0000-000000000001';
        $this->svc->saasAuth = new FakeSaasAuth(userLoginFn: fn (): array => [
            'user' => ['id' => $uuidUser, 'username' => 'alice', 'email' => 'alice@saas.local'],
            'availableTenants' => [
                ['tenantId' => $uuidTenant1, 'roleIds' => ['admin'], 'status' => 'active'],
                ['tenantId' => '00000000-0000-0000-0000-000000000002', 'roleIds' => ['technician'], 'status' => 'active'],
            ],
            'accessToken' => 'u-at', 'refreshToken' => 'u-rt',
            'userId' => $uuidUser, 'currentTenantId' => null,
        ]);

        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'pw-123']);

        $response->assertOk();
        $body = $response->json();
        $tid = $body['tenants'][0]['tenantId'];
        $this->assertSame($uuidTenant1, $tid);

        // me：resolve_user 必须能解析密码登录签发的 sub；租户与登录响应同体系
        $me = $this->getJson('/api/auth/me', $this->bearer($body['token']));
        $me->assertOk();
        $this->assertSame($tid, $me->json('tenants.0.tenantId'));
        $this->assertCount(count($body['tenants']), $me->json('tenants'));

        // switch-tenant：登录响应里的租户可切 → 200 + 3 段真 JWT（tenant_id=目标租户）
        $switched = $this->postJson('/api/auth/switch-tenant', ['tenantId' => $tid], $this->bearer($body['token']));
        $switched->assertOk();
        $switchedBody = $switched->json();
        $this->assertSame(3, substr_count($switchedBody['token'], '.') + 1);
        $claims = (new JwtService)->verify($switchedBody['token']);
        $this->assertSame($tid, $claims['tenant_id']);
    }

    // ==== me / permissions / menus ====

    #[Test]
    public function me_without_token_is_401(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function me_with_demo_token_returns_directory_tenants_and_default_tenant(): void
    {
        $response = $this->getJson('/api/auth/me', $this->bearer($this->accessToken('USER-A')));

        $response->assertOk();
        $this->assertSame('TENANT-001', $response->json('currentTenantId'));
        $this->assertCount(3, $response->json('tenants'));
        $this->assertSame('city-lab', $response->json('tenants.0.code'));
    }

    #[Test]
    public function me_with_unknown_sub_is_401(): void
    {
        $response = $this->getJson('/api/auth/me', $this->bearer($this->accessToken('ghost')));

        $response->assertUnauthorized();
    }

    #[Test]
    public function permissions_returns_the_11_item_demo_set(): void
    {
        $response = $this->getJson('/api/auth/permissions', $this->bearer($this->accessToken('USER-A')));

        $response->assertOk();
        $this->assertCount(11, $response->json('permissions'));
    }

    #[Test]
    public function menus_without_snapshot_is_503_menus_unavailable_demo_fallback_removed(): void
    {
        $response = $this->getJson('/api/auth/menus', $this->bearer($this->accessToken('USER-A')));

        $response->assertStatus(503);
        $this->assertSame('MENUS_UNAVAILABLE', $response->json('code'));
    }

    #[Test]
    public function menus_after_login_returns_mapped_snapshot_sorted_by_sort_order(): void
    {
        $this->loginAlice();

        $response = $this->getJson('/api/auth/menus', $this->bearer($this->accessToken('USER-A')));

        $response->assertOk();
        $tree = $response->json();
        $this->assertSame(['m0', 'm1'], array_column($tree, 'id'));
        $this->assertSame('工作台', $tree[1]['label']);
        // title nil → code 回退；group 无 icon → resource 兜底
        $child = $tree[1]['children'][0];
        $this->assertSame('summary', $child['label']);
        $this->assertSame('resource', $tree[1]['icon']);
    }

    // ==== refresh（rotate-once + 快照回填） ====

    #[Test]
    public function refresh_rotates_via_saas_and_repopulates_membership_snapshot(): void
    {
        $stubToken = (new JwtService)->issueRefresh('saas-u1', 'old-saas-rt');

        $response = $this->postJson('/api/auth/refresh', ['refreshToken' => $stubToken]);

        $response->assertOk();
        $body = $response->json();
        // tenants 来自 saas memberships + 平台租户名富化（code=tenantKey, name=真名）
        $this->assertSame('city', $body['tenants'][0]['code']);
        $this->assertSame('市检测中心', $body['tenants'][0]['name']);
        $this->assertSame('saas-u1', $body['user']['id']);
        // me() 走 SSO 租户体系（快照命中），currentTenantId 取快照首项
        $me = $this->getJson('/api/auth/me', $this->bearer($body['token']));
        $me->assertOk();
        $this->assertSame('ten-saas-1', $me->json('currentTenantId'));
        $this->assertCount(2, $me->json('tenants'));
        $this->assertNotSame($stubToken, $body['refreshToken']);
    }

    #[Test]
    public function refresh_with_garbage_token_is_401(): void
    {
        $this->postJson('/api/auth/refresh', ['refreshToken' => 'not-a-jwt'])->assertUnauthorized();
    }

    #[Test]
    public function refresh_with_access_type_token_is_401(): void
    {
        $response = $this->postJson('/api/auth/refresh', ['refreshToken' => $this->accessToken('USER-A')]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function refresh_without_saas_refresh_token_claim_is_401(): void
    {
        $token = (new JwtService)->issueRefresh('USER-A', '');

        $this->postJson('/api/auth/refresh', ['refreshToken' => $token])->assertUnauthorized();
    }

    // ==== SSO authorize / callback ====

    #[Test]
    public function sso_authorize_echoes_caller_redirect_uri_and_state_adr_0019_no_fallback(): void
    {
        // query 参数名是 snake_case redirect_uri（OAuth RFC 6749 §4.1.1 惯例，
        // springboot AuthApi @RequestParam("redirect_uri") 镜像）。
        // 2026-09-29 live 实锤：读驼峰 redirectUri → ct 全参请求 400。
        $response = $this->getJson('/api/auth/sso/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => 'lab-management',
            'redirect_uri' => 'http://localhost:5203/login',
            'state' => 'csrf-123',
        ]));

        $response->assertOk();
        $body = $response->json();
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%3A5203%2Flogin', $body['authorizeUrl']);
        $this->assertStringContainsString('state=csrf-123', $body['authorizeUrl']);
        $this->assertStringContainsString('client_id=lab-management', $body['authorizeUrl']);
        $this->assertSame('csrf-123', $body['state']);
    }

    #[Test]
    public function sso_authorize_without_redirect_uri_is_400(): void
    {
        $response = $this->getJson('/api/auth/sso/authorize?'.http_build_query(['state' => 'x']));

        $response->assertBadRequest();
    }

    #[Test]
    public function sso_callback_exchanges_code_upserts_user_by_email_and_enriches_tenant_names(): void
    {
        $response = $this->postJson('/api/auth/sso/callback', [
            'code' => 'one-time-code',
            'redirectUri' => 'http://localhost:5203/login',
        ]);

        $response->assertOk();
        $body = $response->json();
        $this->assertSame('saas-u1', $body['user']['id']);
        $this->assertSame('bob@lab.dev', $body['user']['username']);
        $this->assertSame('市检测中心', $body['tenants'][0]['name']);
        // me() 走 SSO 体系：membership 快照命中（回调整时已填充）
        $me = $this->getJson('/api/auth/me', $this->bearer($body['token']));
        $me->assertOk();
        $this->assertSame('ten-saas-1', $me->json('currentTenantId'));
    }

    #[Test]
    public function me_with_empty_membership_snapshot_returns_200_empty_not_refresh_required_i2_family(): void
    {
        // tracker 5.111：saas 侧用户无任何 membership → 回调缓存 []（非 null）；
        // 家族裁定（I2 双栈 4893341/2437aa7）空快照=200 tenants=[] currentTenantId=""（空即空），
        // 不得误判 miss 走 refresh-required 401 / demo 目录降级
        $this->svc->saasMe = new FakeSaasMe(listMyTenantsFn: fn (): array => []);

        $callback = $this->postJson('/api/auth/sso/callback', [
            'code' => 'one-time-code',
            'redirectUri' => 'http://localhost:5203/login',
        ]);
        $callback->assertOk();
        $uid = $callback->json('user.id');

        $response = $this->getJson('/api/auth/me', $this->bearer($this->accessToken($uid)));
        $response->assertOk();
        $this->assertSame([], $response->json('tenants'));
        $this->assertSame('', $response->json('currentTenantId'));
    }

    // ==== switch-tenant / logout ====

    #[Test]
    public function switch_tenant_reissues_token_with_target_claim(): void
    {
        $response = $this->postJson(
            '/api/auth/switch-tenant',
            ['tenantId' => 'TENANT-002'],
            $this->bearer($this->accessToken('USER-A'))
        );

        $response->assertOk();
        $body = $response->json();
        $claims = (new JwtService)->verify($body['token']);
        $this->assertSame('TENANT-002', $claims['tenant_id']);
        $this->assertNotEmpty($body['token']);
        $this->assertNotEmpty($body['refreshToken']);
    }

    #[Test]
    public function switch_tenant_unknown_id_is_404(): void
    {
        $response = $this->postJson(
            '/api/auth/switch-tenant',
            ['tenantId' => 'TENANT-404'],
            $this->bearer($this->accessToken('USER-A'))
        );

        $response->assertNotFound();
    }

    #[Test]
    public function logout_is_204_with_token_and_401_without(): void
    {
        $response = $this->postJson('/api/auth/logout', [], $this->bearer($this->accessToken('USER-A')));
        $response->assertNoContent();

        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    // ==== 助手（rails private 段同位） ====

    private function loginAlice(): array
    {
        $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'dev123456']);
        $response->assertOk();

        /** @var array<string, mixed> */
        return $response->json();
    }

    private function accessToken(string $sub): string
    {
        return (new JwtService)->issueAccess($sub);
    }

    /** @return array<string, string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
