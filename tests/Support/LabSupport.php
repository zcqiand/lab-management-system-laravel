<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\InspectionObject;
use App\Models\InspectionParameter;
use App\Models\InspectionSpecialty;
use App\Models\InspectionStandard;
use App\Services\Auth\JwtService;
use App\Support\Time;
use Illuminate\Support\Env;
use Illuminate\Testing\TestResponse;

// 字典/业务域集成测试共享助手（rails test/integration/lab_support.rb +
// test/support/business_helpers.rb 公共面镜像）：
// - token 用 JwtService issue_access 直签（Bearer typ=access）
// - 数据真库链（lab_test），测试类 use DatabaseTransactions 事务回滚
// - 业务码全带随机后缀，防 lab_test 残留行串扰
trait LabSupport
{
    // rails LabSupport::TENANT_A / TENANT_B（tenant 隔离断言用，库里无需真实租户行）
    public const TENANT_A = 'a0000000-0000-0000-0000-00000000000a';

    public const TENANT_B = 'b0000000-0000-0000-0000-00000000000b';

    // rails BusinessApiTest#tenant_id 镜像：ENV.fetch('LAB_SAAS_DEFAULT_TENANT_ID')
    public function tenantId(): string
    {
        $tenantId = Env::get('LAB_SAAS_DEFAULT_TENANT_ID');
        self::assertNotEmpty($tenantId, 'LAB_SAAS_DEFAULT_TENANT_ID 缺失——.env.test 必有（rails ENV.fetch 镜像）');

        return (string) $tenantId;
    }

    // token 直签缺省租户：rails LabSupport#auth_header(tid = TENANT_A)。业务域测试类
    // （BusinessApiTest 口径，auth_headers(tid = ENV tenant)）覆写本方法。
    public function defaultAuthTenantId(): string
    {
        return self::TENANT_A;
    }

    // 异租户 id（只用于 tenant-scoping 断言，库里无需真实租户行——tenant_id 是裸 text 列）
    public function otherTenantId(): string
    {
        return '11111111-1111-1111-1111-111111111111';
    }

    /** @return array<string, string> */
    public function authHeader(?string $tenantId = null): array
    {
        $token = (new JwtService)->issueAccess('tester', $tenantId ?? $this->defaultAuthTenantId());

        return ['Authorization' => 'Bearer '.$token];
    }

    /** @return array<string, string> */
    public function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // 唯一业务码（每测试现场生成，落库行事务回滚，不依赖前后缀清理）
    public function uniqCode(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(4));
    }

    /**
     * FK 锚点四件套：specialty/object/parameter/standard 各一行（互链满足全部 FK）。
     *
     * @return array{specialty: InspectionSpecialty, object: InspectionObject, parameter: InspectionParameter, standard: InspectionStandard}
     */
    public function seedDictionary(): array
    {
        $now = Time::nowIso();
        $specialty = InspectionSpecialty::query()->create([
            'code' => $this->uniqCode('tst-sp'), 'official_no' => 'ON-tst', 'name' => '专项tst',
            'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $object = InspectionObject::query()->create([
            'code' => $this->uniqCode('tst-obj'), 'inspection_specialty_code' => $specialty->code,
            'source_project_no' => 'P-tst', 'source_project_name' => '项目tst', 'name' => '对象tst',
            // sort_order 压尾：事务回滚在进程被杀时不生效，残留行 sort_order=0 会
            // 抢占 lab-react CategoryDictList 默认选中位（首对象）令 /models-list 空——
            // aspnetcore EfStorePgTest 5.74 同款事故同款修法（OBJ-PG-T sort_order 9999）。
            'sort_order' => 9999, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $parameter = InspectionParameter::query()->create([
            'code' => $this->uniqCode('tst-ip'), 'name' => '参数tst', 'raw_name' => 'raw-tst',
            'canonical_name' => 'can-tst', 'aliases' => [], 'sort_order' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $standard = InspectionStandard::query()->create([
            'code' => $this->uniqCode('tst-std'), 'name' => '标准tst', 'status' => 'active',
            'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['specialty' => $specialty, 'object' => $object, 'parameter' => $parameter, 'standard' => $standard];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apiPost(string $path, array $payload, ?string $tenantId = null): TestResponse
    {
        return $this->postJson($path, $payload, $this->authHeader($tenantId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apiPut(string $path, array $payload, ?string $tenantId = null): TestResponse
    {
        return $this->putJson($path, $payload, $this->authHeader($tenantId));
    }

    public function apiGet(string $path, ?string $tenantId = null): TestResponse
    {
        return $this->getJson($path, $this->authHeader($tenantId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function apiPatch(string $path, array $payload, ?string $tenantId = null): TestResponse
    {
        return $this->patchJson($path, $payload, $this->authHeader($tenantId));
    }

    public function apiDelete(string $path, ?string $tenantId = null): TestResponse
    {
        return $this->deleteJson($path, [], $this->authHeader($tenantId));
    }
}
