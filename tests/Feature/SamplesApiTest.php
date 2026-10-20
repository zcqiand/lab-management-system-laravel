<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InspectionReportName;
use App\Models\Sample;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M03.F02/F03 样品域集成测试（行为真源 = lab-springboot SampleService/SampleMapper +
// lab-ct samples(.write).test.ts；镜像 lab rails test/integration/samples_api_test.rb
// 全 9 用例，不加戏）。真库链（lab_test）事务回滚；token 直签。
final class SamplesApiTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // rails BusinessApiTest 口径：token 缺省租户 = env LAB_SAAS_DEFAULT_TENANT_ID
    public function defaultAuthTenantId(): string
    {
        return $this->tenantId();
    }

    private string $reportNameCode = '';

    private string $contractId = '';

    private string $receiptId = '';

    // rails setup 镜像：报告名 + 合同 + 接样单（样品挂接样单）
    protected function setUp(): void
    {
        parent::setUp();
        $this->reportNameCode = $this->createReportName();
        $this->contractId = $this->createContractApi();
        $this->receiptId = $this->createReceiptApi($this->contractId, $this->reportNameCode);
    }

    #[Test]
    public function missing_bearer_token_is_401(): void
    {
        $this->getJson('/api/samples')->assertUnauthorized();
    }

    #[Test]
    public function create_returns_s_prefixed_id_with_ext_echo_and_missing_receipt_fk_is_404(): void
    {
        $created = $this->apiPost('/api/samples', [
            'receiptId' => $this->receiptId, 'sampleCode' => 'SMP-1', 'sampleName' => '试块',
            'ext' => ['level' => 'P6'],
        ])->assertOk()->json();
        $this->assertMatchesRegularExpression('/\AS-[\da-f-]{36}\z/', (string) $created['id']);
        $this->assertSame('SMP-1', $created['sampleCode']);
        $this->assertSame(['level' => 'P6'], $created['ext']);
        $this->assertSame($this->tenantId(), $created['tenantId']);

        $response = $this->apiPost('/api/samples', [
            'receiptId' => '00000000-0000-0000-0000-00000000dead', 'sampleCode' => 'SMP-X',
        ]);
        $response->assertNotFound();
        $this->assertMatchesRegularExpression('/Receipt not found/', (string) $response->json('message'));
    }

    #[Test]
    public function list_envelope_defaults_with_receipt_id_keyword_filters_and_composite_sort(): void
    {
        $id = $this->createSampleApi();

        // 排序镜像：createdAt DESC, sampleCode asc（同秒挂 sample_code 升序决胜）
        $idZ = $this->createSampleApi('SMP-Z');
        $idA = $this->createSampleApi('SMP-A');
        $newer = '2026-02-01T00:00:00+00:00';
        $older = '2026-01-01T00:00:00+00:00';
        Sample::query()->whereKey($id)->update(['created_at' => $newer]);
        Sample::query()->whereKey($idZ)->update(['created_at' => $older]);
        Sample::query()->whereKey($idA)->update(['created_at' => $older]);

        $envelope = $this->apiGet('/api/samples')->assertOk()->json();
        foreach (['items', 'page', 'pageSize', 'total'] as $key) {
            $this->assertArrayHasKey($key, $envelope);
        }
        $this->assertSame(1, $envelope['page']);
        $this->assertSame(20, $envelope['pageSize']);
        $this->assertSame([$id, $idA, $idZ], array_column($envelope['items'], 'id'));

        $byReceipt = $this->apiGet("/api/samples?receiptId={$this->receiptId}")->assertOk()->json();
        $this->assertContains($id, array_column($byReceipt['items'], 'id'));

        $noHit = $this->apiGet('/api/samples?keyword=NO-SUCH-CODE')->assertOk()->json();
        $this->assertSame([], $noHit['items']);
    }

    #[Test]
    public function tenant_scoping_other_tenant_list_hidden_and_detail_404(): void
    {
        $id = $this->createSampleApi();

        $list = $this->apiGet('/api/samples', $this->otherTenantId());
        $list->assertOk();
        $this->assertNotContains($id, array_column($list->json('items'), 'id'));

        $this->apiGet("/api/samples/{$id}", $this->otherTenantId())->assertNotFound();
    }

    #[Test]
    public function put_updates_sample_name_and_sample_code_is_not_updatable(): void
    {
        $id = $this->createSampleApi();

        $parsed = $this->apiPut("/api/samples/{$id}", [
            'sampleName' => '改名试块', 'sampleCode' => 'HACKED',
        ])->assertOk()->json();

        $this->assertSame('改名试块', $parsed['sampleName']);
        $this->assertSame('SMP-1', $parsed['sampleCode']);
    }

    #[Test]
    public function put_ext_writes_real_sample_and_get_readback_is_consistent(): void
    {
        $id = $this->createSampleApi();

        $parsed = $this->apiPut("/api/samples/{$id}/ext", [
            'ext' => ['ctProbe' => 'val-42'],
        ])->assertOk()->json();
        $this->assertSame(['ctProbe' => 'val-42'], $parsed['ext']);

        $readback = $this->apiGet("/api/samples/{$id}")->assertOk()->json();
        $this->assertSame('val-42', $readback['ext']['ctProbe']);
    }

    #[Test]
    public function put_ext_missing_ext_key_is_400_ext_is_required(): void
    {
        $id = $this->createSampleApi();

        $response = $this->apiPut("/api/samples/{$id}/ext", []);

        $response->assertBadRequest();
        $this->assertMatchesRegularExpression('/ext is required/', (string) $response->json('message'));
    }

    #[Test]
    public function put_ext_unknown_id_is_404(): void
    {
        // springboot updateExt 镜像：带 ext 打死 id（ext 校验通过后走查无 404）
        $this->apiPut('/api/samples/00000000-0000-0000-0000-00000000dead/ext', [
            'ext' => ['k' => 'v'],
        ])->assertNotFound();
    }

    #[Test]
    public function delete_is_204_then_repeat_is_404(): void
    {
        $id = $this->createSampleApi();
        $this->apiDelete("/api/samples/{$id}")->assertNoContent();
        $this->apiDelete("/api/samples/{$id}")->assertNotFound();
    }

    // ==== 助手（rails business_helpers 私有段同位） ====

    private function createReportName(): string
    {
        $now = Time::nowIso();
        $reportName = InspectionReportName::query()->create([
            'code' => $this->uniqCode('RN-T'), 'name' => '报告名-'.bin2hex(random_bytes(3)),
            'summary_name' => '混凝土抗压强度', 'created_at' => $now, 'updated_at' => $now,
        ]);

        return (string) $reportName->code;
    }

    private function createContractApi(): string
    {
        $suffix = bin2hex(random_bytes(4));
        $created = $this->apiPost('/api/contracts', [
            'contractCode' => "CT-T-{$suffix}", 'clientUnit' => "unit-{$suffix}",
            'projectName' => "proj-{$suffix}", 'constructionUnit' => "cons-{$suffix}",
            'witnessUnit' => "wu-{$suffix}", 'witness' => "w-{$suffix}",
        ]);
        $created->assertOk();

        return (string) $created->json('id');
    }

    private function createReceiptApi(string $contractId, string $categoryCode): string
    {
        $suffix = bin2hex(random_bytes(4));
        $created = $this->apiPost('/api/receipts', [
            'contractId' => $contractId, 'commissionCode' => "WT-T-{$suffix}",
            'commissionDate' => '2026-09-20', 'categoryCode' => $categoryCode,
            'receivedBy' => 'alice', 'sampleSource' => 'client', 'testCategory' => 'concrete',
        ]);
        $created->assertOk();

        return (string) $created->json('id');
    }

    private function createSampleApi(string $sampleCode = 'SMP-1'): string
    {
        $created = $this->apiPost('/api/samples', [
            'receiptId' => $this->receiptId, 'sampleCode' => $sampleCode,
        ]);
        $created->assertOk();

        return (string) $created->json('id');
    }
}
