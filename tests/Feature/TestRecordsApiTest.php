<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InspectionParameter;
use App\Models\InspectionReportName;
use App\Models\TestRecord;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M03.F03 检测记录域集成测试（行为真源 = lab-springboot TestRecordService +
// TestRecordMapper；lab-ct test-records(.write).test.ts；镜像 lab rails
// test/integration/test_records_api_test.rb 全 11 用例，不加戏）。真库链（lab_test）
// 事务回滚；token 直签。FK 走 DB 约束→400 与 receipts/samples 的显式查 404 是有意分叉。
final class TestRecordsApiTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // rails BusinessApiTest 口径：token 缺省租户 = env LAB_SAAS_DEFAULT_TENANT_ID
    public function defaultAuthTenantId(): string
    {
        return $this->tenantId();
    }

    private string $reportNameCode = '';

    private string $parameterCode = '';

    private string $contractId = '';

    private string $receiptId = '';

    private string $sampleId = '';

    // rails setup 镜像：报告名 + 参数 + 合同 + 接样单 + 样品
    protected function setUp(): void
    {
        parent::setUp();
        $this->reportNameCode = $this->createReportName();
        $this->parameterCode = $this->createParameter();
        $this->contractId = $this->createContractApi();
        $this->receiptId = $this->createReceiptApi($this->contractId, $this->reportNameCode);
        $this->sampleId = $this->createSampleApi($this->receiptId);
    }

    #[Test]
    public function missing_bearer_token_is_401(): void
    {
        $this->getJson('/api/test-records')->assertUnauthorized();
    }

    #[Test]
    public function create_returns_tr_prefixed_id_with_field_echo_and_four_required_missing_is_400(): void
    {
        $created = $this->apiPost('/api/test-records', [
            'sampleId' => $this->sampleId, 'parameterCode' => $this->parameterCode,
            'requirement' => '≥30MPa', 'result' => '25.1', 'verdict' => 'fail',
        ])->assertOk()->json();
        $this->assertMatchesRegularExpression('/\ATR-[\da-f-]{36}\z/', (string) $created['id']);
        $this->assertSame($this->sampleId, $created['sampleId']);
        $this->assertSame($this->parameterCode, $created['parameterCode']);
        $this->assertSame('fail', $created['verdict']);
        $this->assertSame($this->tenantId(), $created['tenantId']);

        $response = $this->apiPost('/api/test-records', ['sampleId' => $this->sampleId]);
        $response->assertBadRequest();
        $this->assertMatchesRegularExpression(
            '/sampleId, parameterCode, requirement and result are required/',
            (string) $response->json('message')
        );
    }

    #[Test]
    public function create_with_missing_sample_fk_is_400_constraint_violation(): void
    {
        $this->apiPost('/api/test-records', [
            'sampleId' => '00000000-0000-0000-0000-00000000dead',
            'parameterCode' => $this->parameterCode, 'requirement' => 'r', 'result' => 'x',
        ])->assertBadRequest();
    }

    #[Test]
    public function create_with_missing_parameter_fk_is_400_constraint_violation(): void
    {
        $this->apiPost('/api/test-records', [
            'sampleId' => $this->sampleId, 'parameterCode' => 'IP-NO-SUCH',
            'requirement' => 'r', 'result' => 'x',
        ])->assertBadRequest();
    }

    #[Test]
    public function list_envelope_defaults_with_sample_id_filter_and_composite_sort(): void
    {
        $id = $this->createRecordApi();
        $otherSampleId = $this->createSampleApi($this->receiptId, 'SMP-2');
        $otherRecordId = $this->apiPost('/api/test-records', [
            'sampleId' => $otherSampleId, 'parameterCode' => $this->parameterCode,
            'requirement' => 'r', 'result' => 'x',
        ])->assertOk()->json('id');

        // 排序镜像：updatedAt DESC, id asc（同秒挂 id 升序决胜）
        $older = '2026-01-01T00:00:00+00:00';
        $newer = '2026-02-01T00:00:00+00:00';
        TestRecord::query()->whereKey($id)->update(['updated_at' => $newer]);
        TestRecord::query()->whereKey($otherRecordId)->update(['updated_at' => $newer]);

        $envelope = $this->apiGet('/api/test-records')->assertOk()->json();
        foreach (['items', 'page', 'pageSize', 'total'] as $key) {
            $this->assertArrayHasKey($key, $envelope);
        }
        $this->assertSame(1, $envelope['page']);
        $this->assertSame(20, $envelope['pageSize']);
        // 同秒（被 pin 成同一 updated_at）的记录挂 id 升序决胜
        $expected = [$id, $otherRecordId];
        sort($expected, SORT_STRING);
        $this->assertSame($expected, array_column($envelope['items'], 'id'));

        $bySample = $this->apiGet("/api/test-records?sampleId={$this->sampleId}")->assertOk()->json();
        $this->assertContains($id, array_column($bySample['items'], 'id'));

        $noHit = $this->apiGet('/api/test-records?sampleId=00000000-0000-0000-0000-00000000dead')
            ->assertOk()->json();
        $this->assertSame([], $noHit['items']);
    }

    #[Test]
    public function tenant_scoping_other_tenant_list_hidden_and_detail_404(): void
    {
        $id = $this->createRecordApi();

        $list = $this->apiGet('/api/test-records', $this->otherTenantId());
        $list->assertOk();
        $this->assertNotContains($id, array_column($list->json('items'), 'id'));

        $this->apiGet("/api/test-records/{$id}", $this->otherTenantId())->assertNotFound();
    }

    #[Test]
    public function unknown_id_is_404_not_found_code(): void
    {
        $response = $this->apiGet('/api/test-records/00000000-0000-0000-0000-00000000dead');

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
    }

    #[Test]
    public function put_partial_updates_result_and_requirement_and_sample_id_is_not_updatable(): void
    {
        $id = $this->createRecordApi();

        $parsed = $this->apiPut("/api/test-records/{$id}", [
            'result' => '31.2', 'sampleId' => 'HACKED',
        ])->assertOk()->json();

        $this->assertSame('31.2', $parsed['result']);
        $this->assertSame($this->sampleId, $parsed['sampleId']);
    }

    #[Test]
    public function patch_verdict_overrides_and_readback_is_consistent_and_null_writes(): void
    {
        $id = $this->createRecordApi();

        $parsed = $this->apiPatch("/api/test-records/{$id}/verdict", ['verdict' => 'pass'])
            ->assertOk()->json();
        $this->assertSame('pass', $parsed['verdict']);

        $readback = $this->apiGet("/api/test-records/{$id}")->assertOk()->json();
        $this->assertSame('pass', $readback['verdict']);

        // strict 口径：verdict 可直写 null（改判撤销）；null 键被 normalize 剔除不出线
        $this->apiPatch("/api/test-records/{$id}/verdict", ['verdict' => null])->assertOk();
        $this->assertArrayNotHasKey(
            'verdict',
            $this->apiGet("/api/test-records/{$id}")->assertOk()->json()
        );
    }

    #[Test]
    public function patch_verdict_unknown_id_is_404(): void
    {
        $this->apiPatch('/api/test-records/00000000-0000-0000-0000-00000000dead/verdict', [
            'verdict' => 'pass',
        ])->assertNotFound();
    }

    #[Test]
    public function delete_is_204_then_repeat_is_404(): void
    {
        $id = $this->createRecordApi();
        $this->apiDelete("/api/test-records/{$id}")->assertNoContent();
        $this->apiDelete("/api/test-records/{$id}")->assertNotFound();
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

    private function createParameter(): string
    {
        $now = Time::nowIso();
        $parameter = InspectionParameter::query()->create([
            'code' => $this->uniqCode('IP-T'), 'name' => '参数-'.bin2hex(random_bytes(3)),
            'raw_name' => 'raw', 'canonical_name' => 'canonical', 'aliases' => [],
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return (string) $parameter->code;
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

    private function createSampleApi(string $receiptId, string $sampleCode = 'SMP-1'): string
    {
        $created = $this->apiPost('/api/samples', [
            'receiptId' => $receiptId, 'sampleCode' => $sampleCode,
        ]);
        $created->assertOk();

        return (string) $created->json('id');
    }

    private function createRecordApi(): string
    {
        $created = $this->apiPost('/api/test-records', [
            'sampleId' => $this->sampleId, 'parameterCode' => $this->parameterCode,
            'requirement' => '≥30MPa', 'result' => '25.1',
        ]);
        $created->assertOk();

        return (string) $created->json('id');
    }
}
