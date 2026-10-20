<?php

declare(strict_types=1);

namespace App\Services\Auth;

// 配置式 demo 目录（rails app/services/auth/user_directory.rb 逐语义移植；lab
// springboot ConfigUserDirectory 同源）。lab 库无身份表：用户/租户在内存
// （数据镜像 lab-msw seeds）。
// - 用户：alice（USER-A, roleCode=admin）——口令校验已代理 saas IdP，本目录只承担
//   SSO/refresh/me 的用户与租户查找
// - 租户：TENANT-001 city-lab / TENANT-002 district-lab / TENANT-003 third-party（冻结常量）
// - SSO 用户 upsert 进内存；per-user saas refresh token（rotate-once reload 用）
final class UserDirectory
{
    /** @var list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}> */
    public const TENANTS = [
        ['tenant_id' => 'TENANT-001', 'code' => 'city-lab', 'name' => '市住建工程质量检测中心', 'role_ids' => ['admin']],
        ['tenant_id' => 'TENANT-002', 'code' => 'district-lab', 'name' => '区检测站', 'role_ids' => ['technician']],
        ['tenant_id' => 'TENANT-003', 'code' => 'third-party', 'name' => '第三方检测实验室', 'role_ids' => ['viewer']],
    ];

    /** @var array{id: string, username: string, display_name: string, role_code: string} */
    private const DEMO_USER = ['id' => 'USER-A', 'username' => 'alice', 'display_name' => '管理员', 'role_code' => 'admin'];

    /** @var array<string, array{id: string, username: string, display_name: string, role_code: string}> */
    private array $upserted = [];

    /** @var array<string, string> user_id => saas refresh token（进程内） */
    private array $saasRefreshTokens = [];

    /** @return array{id: string, username: string, display_name: string, role_code: string}|null */
    public function findByUsername(string $username): ?array
    {
        if ($username === self::DEMO_USER['username']) {
            return self::DEMO_USER;
        }

        return $this->findFirst(
            fn (array $user): bool => $user['username'] === $username
        );
    }

    /** @return array{id: string, username: string, display_name: string, role_code: string}|null */
    public function findByEmail(string $email): ?array
    {
        // ADR-0008 username/email 桥接：SSO 回查按 email，demo 用户 username 即命中。
        if ($email === self::DEMO_USER['username']) {
            return self::DEMO_USER;
        }

        return $this->findFirst(
            fn (array $user): bool => $user['username'] === $email
        );
    }

    /** @return array{id: string, username: string, display_name: string, role_code: string}|null */
    public function findById(string $id): ?array
    {
        if ($id === self::DEMO_USER['id']) {
            return self::DEMO_USER;
        }

        return $this->findFirst(
            fn (array $user): bool => $user['id'] === $id
        );
    }

    /**
     * rails tenants_of 镜像：lab 三租户全量（无 per-user membership 过滤）。
     *
     * @return list<array{tenant_id: string, code: string, name: string, role_ids: list<string>}>
     */
    public function tenantsOf(string $username): array
    {
        return self::TENANTS;
    }

    /**
     * 家族约定：JWT claim tenant_id 缺省回退默认租户 TENANT-001（JwtGuard 消费；
     * rails JwtGuard#current_tenant_id 镜像）。硬编码——禁 LAB_SAAS_DEFAULT_TENANT_ID
     * env 兜底：业务租户域与 saas 服务账号域分离（2026-09-29 live 实锤）。
     *
     * @return array{tenant_id: string, code: string, name: string, role_ids: list<string>}
     */
    public function defaultTenant(): array
    {
        return self::TENANTS[0];
    }

    /** @return array{tenant_id: string, code: string, name: string, role_ids: list<string>}|null */
    public function findTenantById(string $tenantId): ?array
    {
        foreach (self::TENANTS as $tenant) {
            if ($tenant['tenant_id'] === $tenantId) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * 首次 SSO 落地 upsert（内存实现；换 DB 实现时接口不变）：email 作键，命中即复用
     * （rails @upserted[email] ||= 镜像，首写为准）；role_code 空 → 'viewer'。
     *
     * @return array{id: string, username: string, display_name: string, role_code: string}
     */
    public function upsert(string $id, string $email, string $displayName, ?string $roleCode): array
    {
        if (isset($this->upserted[$email])) {
            return $this->upserted[$email];
        }

        return $this->upserted[$email] = [
            'id' => $id,
            'username' => $email,
            'display_name' => $displayName,
            'role_code' => $roleCode !== null && trim($roleCode) !== '' ? $roleCode : 'viewer',
        ];
    }

    // per-user saas refresh token（rotate-once reload 用）：blank guard 镜像 rails
    // return if user_id.blank? || token.blank?——空串不写。
    public function setSaasRefreshToken(string $userId, string $token): void
    {
        if ($userId === '' || $token === '') {
            return;
        }
        $this->saasRefreshTokens[$userId] = $token;
    }

    public function getSaasRefreshToken(string $userId): ?string
    {
        if ($userId === '') {
            return null;
        }

        return $this->saasRefreshTokens[$userId] ?? null;
    }

    /**
     * @param  callable(array{id: string, username: string, display_name: string, role_code: string}): bool  $predicate
     * @return array{id: string, username: string, display_name: string, role_code: string}|null
     */
    private function findFirst(callable $predicate): ?array
    {
        foreach ($this->upserted as $user) {
            if ($predicate($user)) {
                return $user;
            }
        }

        return null;
    }
}
