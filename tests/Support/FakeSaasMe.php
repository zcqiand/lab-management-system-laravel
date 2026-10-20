<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Auth\SaasMeClient;
use Closure;

// saas /me 系替身（rails stub_saas_me 同位）：缺省桩返回 whoami（bob/ten-saas-1）+
// memberships（双 saas 租户）+ 菜单树 + 平台租户名表；单方法覆盖走构造 Closure。
final class FakeSaasMe extends SaasMeClient
{
    // memberships 替身（saas 真租户形态：ten- 前缀非 demo TENANT-00x）
    public const MANAGED_TENANTS = [
        ['tenantId' => 'ten-saas-1', 'roleIds' => ['r1']],
        ['tenantId' => 'ten-saas-2', 'roleIds' => []],
    ];

    // 平台租户名表（display-name 富化数据源）
    public const PLATFORM_TENANTS = [
        ['id' => 'ten-saas-1', 'name' => '市检测中心', 'tenantKey' => 'city'],
        ['id' => 'ten-saas-2', 'name' => '区检测站', 'tenantKey' => 'district'],
    ];

    // saas EffectiveMenuNode 树（title nil/code 回退、group icon 兜底、sortOrder 乱序）
    public const SAAS_MENU_TREE = [
        ['id' => 'm1', 'code' => 'dash', 'title' => '工作台', 'type' => 'group',
            'sortOrder' => 2, 'children' => [
                ['id' => 'm11', 'code' => 'summary', 'title' => null, 'type' => 'page',
                    'sortOrder' => 1, 'children' => []],
            ]],
        ['id' => 'm0', 'code' => 'contract', 'title' => '合同', 'type' => 'page',
            'sortOrder' => 1, 'children' => []],
    ];

    private ?Closure $whoamiFn;

    private ?Closure $listMyTenantsFn;

    private ?Closure $listMyMenusFn;

    private ?Closure $listPlatformTenantsFn;

    public function __construct(
        ?Closure $whoamiFn = null,
        ?Closure $listMyTenantsFn = null,
        ?Closure $listMyMenusFn = null,
        ?Closure $listPlatformTenantsFn = null
    ) {
        $this->whoamiFn = $whoamiFn;
        $this->listMyTenantsFn = $listMyTenantsFn;
        $this->listMyMenusFn = $listMyMenusFn;
        $this->listPlatformTenantsFn = $listPlatformTenantsFn;
    }

    public function whoami(string $saasAccessToken): array
    {
        if ($this->whoamiFn !== null) {
            $fn = $this->whoamiFn;

            /** @var array<string, mixed> */
            return $fn($saasAccessToken);
        }

        return ['id' => 'saas-u1', 'email' => 'bob@lab.dev', 'displayName' => '鲍勃',
            'currentTenantId' => 'ten-saas-1'];
    }

    /** @return list<array<string, mixed>> */
    public function listMyTenants(string $saasAccessToken): array
    {
        if ($this->listMyTenantsFn !== null) {
            $fn = $this->listMyTenantsFn;

            /** @var list<array<string, mixed>> */
            return $fn($saasAccessToken);
        }

        return self::MANAGED_TENANTS;
    }

    /** @return list<array<string, mixed>> */
    public function listMyMenus(string $saasAccessToken, string $appCode): array
    {
        if ($this->listMyMenusFn !== null) {
            $fn = $this->listMyMenusFn;

            /** @var list<array<string, mixed>> */
            return $fn($saasAccessToken, $appCode);
        }

        return self::SAAS_MENU_TREE;
    }

    /** @return list<array<string, mixed>> */
    public function listPlatformTenants(string $saasAccessToken): array
    {
        if ($this->listPlatformTenantsFn !== null) {
            $fn = $this->listPlatformTenantsFn;

            /** @var list<array<string, mixed>> */
            return $fn($saasAccessToken);
        }

        return self::PLATFORM_TENANTS;
    }
}
