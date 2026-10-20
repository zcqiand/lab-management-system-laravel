<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\UserDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// UserDirectory 契约（rails app/services/auth/user_directory.rb 逐语义移植）：lab 库无
// 身份表，用户/租户在内存——demo 用户 alice/USER-A/admin + 三租户冻结常量 + SSO
// upsert（email 键，role 空 → viewer）+ per-user saas refresh token 存取（进程内）。
final class UserDirectoryTest extends TestCase
{
    #[Test]
    public function three_tenants_are_frozen_constants(): void
    {
        $tenants = UserDirectory::TENANTS;

        $this->assertSame([
            ['tenant_id' => 'TENANT-001', 'code' => 'city-lab', 'name' => '市住建工程质量检测中心', 'role_ids' => ['admin']],
            ['tenant_id' => 'TENANT-002', 'code' => 'district-lab', 'name' => '区检测站', 'role_ids' => ['technician']],
            ['tenant_id' => 'TENANT-003', 'code' => 'third-party', 'name' => '第三方检测实验室', 'role_ids' => ['viewer']],
        ], $tenants);
    }

    #[Test]
    public function default_tenant_is_tenant_001(): void
    {
        // JWT claim tenant_id 缺省回退真源（JwtGuard 消费；禁 LAB_SAAS_DEFAULT_TENANT_ID
        // env 兜底——业务租户域与 saas 服务账号域分离，2026-09-29 live 实锤）。
        $directory = new UserDirectory;

        $this->assertSame('TENANT-001', $directory->defaultTenant()['tenant_id']);
    }

    #[Test]
    public function tenants_of_returns_all_three_and_find_by_tenant_id(): void
    {
        $directory = new UserDirectory;

        $this->assertCount(3, $directory->tenantsOf('alice'));
        $this->assertSame('third-party', $directory->findTenantById('TENANT-003')['code']);
        $this->assertNull($directory->findTenantById('TENANT-999'));
    }

    #[Test]
    public function demo_user_alice_is_admin(): void
    {
        $directory = new UserDirectory;

        $this->assertSame(
            ['id' => 'USER-A', 'username' => 'alice', 'display_name' => '管理员', 'role_code' => 'admin'],
            $directory->findByUsername('alice')
        );
        $this->assertNull($directory->findByUsername('nobody'));
        $this->assertNull($directory->findById('USER-Z'));
    }

    #[Test]
    public function email_lookup_bridges_to_demo_username(): void
    {
        // ADR-0008 username/email 桥接：SSO 回查按 email，demo 用户 username 即命中。
        $directory = new UserDirectory;

        $this->assertSame('USER-A', $directory->findByEmail('alice')['id']);
    }

    #[Test]
    public function upsert_keys_by_email_and_defaults_role_to_viewer(): void
    {
        $directory = new UserDirectory;

        $first = $directory->upsert('SSO-1', 'bob@example.com', '鲍工', '');

        $this->assertSame('viewer', $first['role_code']); // role 空 → viewer
        $this->assertSame('bob@example.com', $first['username']);

        // 同 email 再 upsert 命中即复用（rails @upserted[email] ||= 镜像，首写为准）。
        $again = $directory->upsert('SSO-2', 'bob@example.com', '鲍工2', 'admin');
        $this->assertSame('SSO-1', $again['id']);
        $this->assertSame('viewer', $again['role_code']);

        $this->assertSame('SSO-1', $directory->findById('SSO-1')['id']);
        $this->assertNull($directory->findById('SSO-2')); // 未落地（||= 语义）
    }

    #[Test]
    public function upsert_keeps_non_blank_role(): void
    {
        $directory = new UserDirectory;

        $user = $directory->upsert('SSO-3', 'carol@example.com', '蔡工', 'technician');

        $this->assertSame('technician', $user['role_code']);
        $this->assertSame('SSO-3', $directory->findByEmail('carol@example.com')['id']);
    }

    #[Test]
    public function saas_refresh_token_round_trips_per_user(): void
    {
        $directory = new UserDirectory;

        $this->assertNull($directory->getSaasRefreshToken('USER-A'));

        $directory->setSaasRefreshToken('USER-A', 'saas-rt-1');
        $this->assertSame('saas-rt-1', $directory->getSaasRefreshToken('USER-A'));

        $directory->setSaasRefreshToken('USER-A', 'saas-rt-2');
        $this->assertSame('saas-rt-2', $directory->getSaasRefreshToken('USER-A')); // rotate 覆写
    }

    #[Test]
    public function blank_ids_and_tokens_are_no_ops(): void
    {
        // rails blank? guard 镜像：user_id/token 空串不写不读。
        $directory = new UserDirectory;

        $directory->setSaasRefreshToken('', 'saas-rt-1');
        $directory->setSaasRefreshToken('USER-A', '');

        $this->assertNull($directory->getSaasRefreshToken(''));
        $this->assertNull($directory->getSaasRefreshToken('USER-A'));
    }
}
