<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M02.F01 合同域集成测试（镜像 lab rails test/integration/contracts_api_test.rb 全 12 用例；
// 行为真源 = lab-springboot ContractService/ContractMapper + lab-ct contracts.test.ts）。
// 真库链（lab_test）事务回滚；token 直签。
final class ContractFlowTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // rails BusinessApiTest 口径：token 缺省租户 = env LAB_SAAS_DEFAULT_TENANT_ID
    // （覆盖 trait 的 LabSupport 口径 TENANT_A）
    public function defaultAuthTenantId(): string
    {
        return $this->tenantId();
    }

    // ==== 缺 token / 兜底租户 ====

    #[Test]
    public function without_bearer_token_is_401_invalid_credentials(): void
    {
        $response = $this->getJson('/api/contracts');

        $response->assertUnauthorized();
        $this->assertSame('INVALID_CREDENTIALS', $response->json('code'));
    }

    #[Test]
    public function token_without_tenant_claim_falls_back_to_directory_default_tenant_001(): void
    {
        // 家族镜像：springboot currentTenantIdOrDefaultStatic 兜底 directory.defaultTenant()
        // （TENANTS[0] = TENANT-001）。2026-09-29 live 实锤：误兜 LAB_SAAS_DEFAULT_TENANT_ID
        // （saas 服务账号域的 GUID）会让 create 回显 tenantId 与 nextjs/springboot 分叉。
        $token = (new JwtService)->issueAccess('tester');
        $response = $this->postJson('/api/contracts', $this->contractPayload(), $this->bearer($token));

        $response->assertOk();
        $this->assertSame('TENANT-001', $response->json('tenantId'));
    }

    // ==== CRUD 全链 ====

    #[Test]
    public function crud_full_chain_create_echoes_camel_and_c_prefixed_id_list_envelope_defaults(): void
    {
        $payload = $this->contractPayload();
        $created = $this->apiPost('/api/contracts', $payload);
        $created->assertOk();
        $body = $created->json();
        $this->assertMatchesRegularExpression('/\AC-[0-9a-f-]{36}\z/', (string) $body['id']);
        $this->assertSame($payload['contractCode'], $body['contractCode']);
        $this->assertSame($payload['projectName'], $body['projectName']);
        $this->assertSame('active', $body['status']);
        $this->assertSame($this->tenantId(), $body['tenantId']);
        $this->assertArrayHasKey('createdAt', $body);
        $this->assertArrayHasKey('updatedAt', $body);

        $list = $this->apiGet('/api/contracts');
        $list->assertOk();
        $envelope = $list->json();
        foreach (['items', 'page', 'pageSize', 'total'] as $key) {
            $this->assertArrayHasKey($key, $envelope);
        }
        $this->assertSame(1, $envelope['page']);
        $this->assertSame(20, $envelope['pageSize']);
        $this->assertIsArray($envelope['items']);
        $this->assertGreaterThanOrEqual(1, $envelope['total']);
        $this->assertContains($body['id'], array_column($envelope['items'], 'id'));
    }

    #[Test]
    public function keyword_filter_matches_only_contract_code_or_project_name_hits(): void
    {
        $id = $this->createContractApi();
        $code = $this->parsedKeyFor($id);

        $items = $this->apiGet('/api/contracts?keyword='.urlencode($code));
        $items->assertOk();
        $body = $items->json();
        $this->assertNotEmpty($body['items']);
        foreach ($body['items'] as $contract) {
            $this->assertSame($id, $contract['id']);
        }
        $this->assertSame(count($body['items']), $body['total']);
    }

    #[Test]
    public function status_filter_archived_hits_and_active_excludes(): void
    {
        $id = $this->createContractApi();

        $updated = $this->apiPut("/api/contracts/{$id}", ['status' => 'archived']);
        $updated->assertOk();
        $this->assertSame('archived', $updated->json('status'));

        $archived = $this->apiGet('/api/contracts?status=archived');
        $archived->assertOk();
        $this->assertContains($id, array_column($archived->json('items'), 'id'));

        $active = $this->apiGet('/api/contracts?status=active');
        $active->assertOk();
        $this->assertNotContains($id, array_column($active->json('items'), 'id'));
    }

    #[Test]
    public function bogus_status_enum_is_400(): void
    {
        $this->apiGet('/api/contracts?status=bogus')->assertBadRequest();
    }

    // ==== tenant 隔离 / 404 形状 ====

    #[Test]
    public function tenant_scoping_other_tenant_list_hidden_and_detail_404(): void
    {
        $id = $this->createContractApi();

        $list = $this->apiGet('/api/contracts', $this->otherTenantId());
        $list->assertOk();
        $this->assertNotContains($id, array_column($list->json('items'), 'id'));

        $this->apiGet("/api/contracts/{$id}", $this->otherTenantId())->assertNotFound();
    }

    #[Test]
    public function unknown_id_is_404_not_found_for_both_uuid_and_non_uuid_shapes(): void
    {
        // 业务域 id 带前缀（C-），NSEE 语义不按裸 UUID 启发式折叠——查无一律 404
        $uuidMiss = $this->apiGet('/api/contracts/00000000-0000-0000-0000-0000000000dead');
        $uuidMiss->assertNotFound();
        $this->assertSame('NOT_FOUND', $uuidMiss->json('code'));

        $this->apiGet('/api/contracts/not-a-real-id')->assertNotFound();
    }

    // ==== 更新 / 删除 ====

    #[Test]
    public function put_partial_update_renames_project_name_and_never_touches_contract_code(): void
    {
        $id = $this->createContractApi();
        $originalCode = $this->parsedKeyFor($id);

        $updated = $this->apiPut("/api/contracts/{$id}", [
            'projectName' => 'renamed-proj', 'contractCode' => 'HACKED',
        ]);
        $updated->assertOk();
        $this->assertSame('renamed-proj', $updated->json('projectName'));
        $this->assertSame($originalCode, $updated->json('contractCode'));
    }

    #[Test]
    public function delete_is_204_then_repeat_is_404(): void
    {
        $id = $this->createContractApi();

        $this->apiDelete("/api/contracts/{$id}")->assertNoContent();
        $this->apiDelete("/api/contracts/{$id}")->assertNotFound();
    }

    // ==== create 校验 ====

    #[Test]
    public function missing_contract_code_is_400_constraint_violation(): void
    {
        $this->apiPost('/api/contracts', ['clientUnit' => 'u', 'projectName' => 'p'])->assertBadRequest();
    }

    #[Test]
    public function bogus_status_on_create_is_400(): void
    {
        $this->apiPost('/api/contracts', $this->contractPayload(status: 'bogus'))->assertBadRequest();
    }

    // ==== 助手（rails business_helpers 私有段同位） ====

    /**
     * @param  string|null  $status  null = 整个键缺失
     * @return array<string, mixed>
     */
    private function contractPayload(?string $status = null): array
    {
        $suffix = bin2hex(random_bytes(4));
        $payload = [
            'contractCode' => "CT-T-{$suffix}", 'clientUnit' => "unit-{$suffix}",
            'projectName' => "proj-{$suffix}", 'constructionUnit' => "cons-{$suffix}",
            'witnessUnit' => "wu-{$suffix}", 'witness' => "w-{$suffix}",
        ];
        if ($status !== null) {
            $payload['status'] = $status;
        }

        return $payload;
    }

    private function createContractApi(): string
    {
        $created = $this->apiPost('/api/contracts', $this->contractPayload());
        $created->assertOk();

        return (string) $created->json('id');
    }

    private function parsedKeyFor(string $id): string
    {
        $got = $this->apiGet("/api/contracts/{$id}");
        $got->assertOk();

        return (string) $got->json('contractCode');
    }
}
