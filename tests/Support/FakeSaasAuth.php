<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Auth\SaasAuthClient;
use Closure;

// saas IdP 替身（rails auth_flow_test 的 define_singleton_method 桩同位）：extends
// 真 client——构造不跑、永不真打 HTTP；缺省桩与 rails stub_service_login /
// stub_user_login_demo / stub_token 同形（stub 忽略账密），账密原值经
// userLoginCalls / serviceLoginCalls 记录（stub_proxy_login 断言透传用）。
// 覆盖单个方法：构造传 Closure（PHP 匿名类整件替换 rails 单方法替身的等价缝）。
final class FakeSaasAuth extends SaasAuthClient
{
    /** @var list<array{username: string, password: string}> */
    public array $serviceLoginCalls = [];

    /** @var list<array{username: string, password: string}> */
    public array $userLoginCalls = [];

    /** @var list<array{grantType: string, code: ?string, refreshToken: ?string, redirectUri: ?string}> */
    public array $tokenCalls = [];

    private ?Closure $serviceLoginFn;

    private ?Closure $userLoginFn;

    private ?Closure $tokenFn;

    public function __construct(?Closure $serviceLoginFn = null, ?Closure $userLoginFn = null, ?Closure $tokenFn = null)
    {
        $this->serviceLoginFn = $serviceLoginFn;
        $this->userLoginFn = $userLoginFn;
        $this->tokenFn = $tokenFn;
    }

    public function serviceLogin(string $username, string $password): array
    {
        $this->serviceLoginCalls[] = ['username' => $username, 'password' => $password];
        if ($this->serviceLoginFn !== null) {
            $fn = $this->serviceLoginFn;

            /** @var array<string, mixed> */
            return $fn($username, $password);
        }

        return ['accessToken' => 'saas-at', 'refreshToken' => 'saas-rt',
            'tokenType' => 'Bearer', 'expiresIn' => 3600, 'scope' => null];
    }

    // 缺省桩与 rails stub_user_login_demo 同形（USER-A/alice/三 demo 租户）。
    public function userLogin(string $username, string $password): array
    {
        $this->userLoginCalls[] = ['username' => $username, 'password' => $password];
        if ($this->userLoginFn !== null) {
            $fn = $this->userLoginFn;

            /** @var array<string, mixed> */
            return $fn($username, $password);
        }

        return [
            'user' => ['id' => 'USER-A', 'username' => 'alice', 'email' => 'alice@saas.local'],
            'availableTenants' => [
                ['tenantId' => 'TENANT-001', 'roleIds' => ['admin'], 'status' => 'active'],
                ['tenantId' => 'TENANT-002', 'roleIds' => ['technician'], 'status' => 'active'],
                ['tenantId' => 'TENANT-003', 'roleIds' => ['viewer'], 'status' => 'active'],
            ],
            'accessToken' => 'dev-access-token', 'refreshToken' => 'dev-refresh-token',
            'userId' => 'USER-A', 'currentTenantId' => null,
        ];
    }

    public function token(string $grantType, ?string $code = null, ?string $refreshToken = null, ?string $redirectUri = null): array
    {
        $this->tokenCalls[] = ['grantType' => $grantType, 'code' => $code,
            'refreshToken' => $refreshToken, 'redirectUri' => $redirectUri];
        if ($this->tokenFn !== null) {
            $fn = $this->tokenFn;

            /** @var array<string, mixed> */
            return $fn($grantType, $code, $refreshToken, $redirectUri);
        }

        return ['accessToken' => 'saas-at2', 'refreshToken' => 'saas-rt2',
            'tokenType' => 'Bearer', 'expiresIn' => 3600, 'scope' => null];
    }
}
