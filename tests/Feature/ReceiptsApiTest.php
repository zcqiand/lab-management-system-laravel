<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InspectionReportName;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M03.F01-F09 接样单域集成测试（行为真源 = lab-springboot SampleReceiptService +
// ReportFlowService + SampleReceiptMapper；镜像 lab rails
// test/integration/receipts_api_test.rb 全 20 用例，不加戏）。真库链（lab_test）
// 事务回滚；token 直签。
final class ReceiptsApiTest extends TestCase
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

    // rails setup 镜像：字典父行 + 合同 + 接样单
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
        $this->getJson('/api/receipts')->assertUnauthorized();
    }

    #[Test]
    public function create_returns_r_prefixed_id_with_initial_receiving_state(): void
    {
        $created = $this->parsedReceipt($this->receiptId);
        $this->assertMatchesRegularExpression('/\AR-[\da-f-]{36}\z/', (string) $created['id']);
        $this->assertSame('receiving', $created['flowStatus']);
        $this->assertSame([], $created['flowHistory']);
        $this->assertSame('', $created['result']);
        $this->assertSame($this->contractId, $created['contractId']);
        $this->assertSame($this->tenantId(), $created['tenantId']);
    }

    #[Test]
    public function create_with_missing_contract_fk_is_404_nsee(): void
    {
        $response = $this->apiPost('/api/receipts', $this->receiptPayload(
            '00000000-0000-0000-0000-0000000000dead',
            $this->reportNameCode
        ));

        $response->assertNotFound();
        $this->assertMatchesRegularExpression('/Contract not found/', (string) $response->json('message'));
    }

    #[Test]
    public function list_envelope_defaults_and_contract_id_keyword_flow_status_filters(): void
    {
        $envelope = $this->apiGet('/api/receipts')->assertOk()->json();
        foreach (['items', 'page', 'pageSize', 'total'] as $key) {
            $this->assertArrayHasKey($key, $envelope);
        }
        $this->assertSame(1, $envelope['page']);
        $this->assertSame(20, $envelope['pageSize']);

        $byContract = $this->apiGet("/api/receipts?contractId={$this->contractId}")->assertOk()->json();
        $this->assertContains($this->receiptId, array_column($byContract['items'], 'id'));

        $noHit = $this->apiGet('/api/receipts?keyword=NO-SUCH-COMMISSION')->assertOk()->json();
        $this->assertSame([], $noHit['items']);

        $receiving = $this->apiGet('/api/receipts?flowStatus=receiving')->assertOk()->json();
        $this->assertContains($this->receiptId, array_column($receiving['items'], 'id'));

        $archived = $this->apiGet('/api/receipts?flowStatus=archived')->assertOk()->json();
        $this->assertNotContains($this->receiptId, array_column($archived['items'], 'id'));

        $this->apiGet('/api/receipts?flowStatus=bogus')->assertBadRequest();
    }

    #[Test]
    public function three_state_filter_not_yet_submitted_are_exclusive_and_submit_writes_last_submitted_by(): void
    {
        $hit = $this->findInFilteredList('not_yet');
        $this->assertNotNull($hit, '新单应命中 not_yet');
        $this->assertSame([], $hit['flowHistory']);

        $result = $this->act('receiving', $this->receiptId, 'submit');
        $this->assertTrue($result[0]['ok']);
        $this->assertSame('task_assignment', $result[0]['flowStatus']);

        $submitted = $this->findInFilteredList('submitted');
        $this->assertNotNull($submitted, 'submit 后应命中 submitted');
        $this->assertSame('tester-op', $submitted['lastSubmittedBy']);

        $this->assertNull(
            $this->findInFilteredList('not_yet'),
            '已提交单不应再命中 not_yet'
        );
    }

    #[Test]
    public function tenant_scoping_other_tenant_list_hidden_and_detail_404(): void
    {
        $list = $this->apiGet('/api/receipts', $this->otherTenantId());
        $list->assertOk();
        $this->assertNotContains($this->receiptId, array_column($list->json('items'), 'id'));

        $this->apiGet("/api/receipts/{$this->receiptId}", $this->otherTenantId())->assertNotFound();
    }

    #[Test]
    public function history_empty_then_submit_entry_records_true_values_and_dead_id_404(): void
    {
        $empty = $this->apiGet("/api/receipts/{$this->receiptId}/history");
        $empty->assertOk();
        $this->assertSame([], $empty->json());

        $this->act('receiving', $this->receiptId, 'submit');
        $history = $this->apiGet("/api/receipts/{$this->receiptId}/history")->assertOk()->json();
        $entry = $history[count($history) - 1];
        $this->assertSame('submit', $entry['action']);
        $this->assertSame('receiving', $entry['from']);
        $this->assertSame('task_assignment', $entry['to']);
        $this->assertSame('tester-op', $entry['operator']);

        $this->apiGet('/api/receipts/00000000-0000-0000-0000-0000000000dead/history')->assertNotFound();
    }

    #[Test]
    public function history_action_records_true_values_through_submit_return_withdraw(): void
    {
        $this->act('receiving', $this->receiptId, 'submit');
        $this->act('assigning', $this->receiptId, 'return');
        $this->assertSame('return', $this->lastHistoryAction());

        $this->act('receiving', $this->receiptId, 'withdraw');
        $this->assertSame('withdraw', $this->lastHistoryAction());

        $history = $this->apiGet("/api/receipts/{$this->receiptId}/history")->assertOk()->json();
        $actions = array_column($history, 'action');
        $this->assertContains('submit', $actions, '首条 submit 条目不被串改');
    }

    #[Test]
    public function last_submitted_by_submit_writes_return_keeps_withdraw_clears(): void
    {
        $this->act('receiving', $this->receiptId, 'submit');
        $this->assertSame('tester-op', $this->parsedReceipt($this->receiptId)['lastSubmittedBy']);

        $this->act('assigning', $this->receiptId, 'return');
        $this->assertSame(
            'tester-op',
            $this->parsedReceipt($this->receiptId)['lastSubmittedBy'],
            'return 保留原值'
        );

        $this->act('receiving', $this->receiptId, 'withdraw');
        $this->assertArrayNotHasKey(
            'lastSubmittedBy',
            $this->parsedReceipt($this->receiptId),
            'withdraw 清空（null 键被 normalize 剔除）'
        );
    }

    #[Test]
    public function withdraw_only_legal_at_receiving_other_stage_is_invalid_transition_err(): void
    {
        $this->act('receiving', $this->receiptId, 'submit');
        $result = $this->act('assigning', $this->receiptId, 'withdraw');
        $this->assertFalse($result[0]['ok']);
        $this->assertMatchesRegularExpression('/Invalid transition/', (string) $result[0]['message']);
        $this->assertSame('task_assignment', $this->parsedReceipt($this->receiptId)['flowStatus']);
    }

    #[Test]
    public function assign_task_advances_receiving_to_task_assignment_with_history_reason(): void
    {
        $created = $this->apiPut("/api/receipts/{$this->receiptId}/task", [
            'assigneeId' => 'USER-A', 'assigneeName' => '张三', 'plannedTestDate' => '2026-10-01',
        ])->assertOk()->json();

        $this->assertSame('task_assignment', $created['flowStatus']);
        $this->assertSame('USER-A', $created['assigneeId']);
        $this->assertSame('张三', $created['assigneeName']);
        $this->assertSame('2026-10-01', $created['plannedTestDate']);
        $entry = $created['flowHistory'][count($created['flowHistory']) - 1];
        $this->assertSame('submit', $entry['action']);
        $this->assertSame('M03.F02 任务分配', $entry['reason']);
        $this->assertSame('张三', $entry['operator']);
    }

    #[Test]
    public function assign_task_on_non_receiving_only_updates_fields(): void
    {
        $receipt2 = $this->createReceiptApi($this->contractId, $this->reportNameCode);
        $this->act('receiving', $receipt2, 'submit');

        $parsed = $this->apiPut("/api/receipts/{$receipt2}/task", ['assigneeName' => '李四'])
            ->assertOk()->json();

        $this->assertSame('task_assignment', $parsed['flowStatus']);
        $this->assertSame('李四', $parsed['assigneeName']);
    }

    #[Test]
    public function seven_stage_submit_chain_advances_to_archived(): void
    {
        $chain = [
            ['receiving', 'task_assignment'], ['assigning', 'data_entry'],
            ['data-entry', 'review'], ['review', 'approval'], ['approve', 'issuance'],
            ['issuance', 'archived'],
        ];
        foreach ($chain as [$stage, $nextStage]) {
            $result = $this->act($stage, $this->receiptId, 'submit');
            if (! $result[0]['ok']) {
                $this->fail("{$stage} submit 应 ok：".(string) ($result[0]['message'] ?? ''));
            }
            $this->assertSame($nextStage, $result[0]['flowStatus']);
            $this->assertSame($nextStage, $this->parsedReceipt($this->receiptId)['flowStatus']);
        }
    }

    #[Test]
    public function stage_mismatch_returns_err_result_not_4xx(): void
    {
        $result = $this->act('review', $this->receiptId, 'submit');

        $this->assertFalse($result[0]['ok']);
        $this->assertSame(
            'Stage mismatch: requires review but is receiving',
            $result[0]['message']
        );
    }

    #[Test]
    public function return_chain_assigning_back_to_receiving(): void
    {
        $this->act('receiving', $this->receiptId, 'submit');
        $result = $this->act('assigning', $this->receiptId, 'return');

        $this->assertTrue($result[0]['ok']);
        $this->assertSame('receiving', $result[0]['flowStatus']);
        $this->assertSame('receiving', $this->parsedReceipt($this->receiptId)['flowStatus']);
    }

    #[Test]
    public function archived_accepts_only_submit_writes_audit_history_return_is_err(): void
    {
        $this->advanceToArchived($this->receiptId);

        $result = $this->act('archived', $this->receiptId, 'submit');
        $this->assertTrue($result[0]['ok']);
        $this->assertSame('archived', $result[0]['flowStatus']);
        $history = $this->apiGet("/api/receipts/{$this->receiptId}/history")->assertOk()->json();
        $this->assertSame('archived: post-archive audit', $history[count($history) - 1]['reason']);

        $rejected = $this->act('archived', $this->receiptId, 'return');
        $this->assertFalse($rejected[0]['ok']);
        $this->assertMatchesRegularExpression('/Action not allowed/', (string) $rejected[0]['message']);
    }

    #[Test]
    public function act_on_unknown_id_returns_200_with_err_result(): void
    {
        $result = $this->act('receiving', '00000000-0000-0000-0000-0000000000dead', 'submit');

        $this->assertFalse($result[0]['ok']);
        $this->assertMatchesRegularExpression('/Receipt not found/', (string) $result[0]['message']);
    }

    #[Test]
    public function operator_contract_required_missing_and_empty_are_400(): void
    {
        $missing = $this->apiPost('/api/receipts/receiving/act', [
            'ids' => ['00000000-0000-0000-0000-0000000000dead'], 'action' => 'submit',
        ]);
        $missing->assertBadRequest();

        $empty = $this->apiPost('/api/receipts/receiving/act', [
            'ids' => ['00000000-0000-0000-0000-0000000000dead'],
            'action' => 'submit', 'operator' => '',
        ]);
        $empty->assertBadRequest();
        $this->assertMatchesRegularExpression('/operator is required/', (string) $empty->json('message'));
    }

    #[Test]
    public function put_partial_update_renames_project_and_contract_id_not_updatable(): void
    {
        $parsed = $this->apiPut("/api/receipts/{$this->receiptId}", [
            'projectName' => 'renamed-proj', 'contractId' => 'HACKED',
        ])->assertOk()->json();

        $this->assertSame('renamed-proj', $parsed['projectName']);
        $this->assertSame($this->contractId, $parsed['contractId']);
    }

    #[Test]
    public function delete_is_204_then_repeat_is_404(): void
    {
        $this->apiDelete("/api/receipts/{$this->receiptId}")->assertNoContent();
        $this->apiDelete("/api/receipts/{$this->receiptId}")->assertNotFound();
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

    /**
     * @return array<string, mixed>
     */
    private function receiptPayload(string $contractId, string $categoryCode): array
    {
        $suffix = bin2hex(random_bytes(4));

        return [
            'contractId' => $contractId, 'commissionCode' => "WT-T-{$suffix}",
            'commissionDate' => '2026-09-20', 'categoryCode' => $categoryCode,
            'receivedBy' => 'alice', 'sampleSource' => 'client', 'testCategory' => 'concrete',
        ];
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
        $created = $this->apiPost('/api/receipts', $this->receiptPayload($contractId, $categoryCode));
        $created->assertOk();

        return (string) $created->json('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function parsedReceipt(string $id): array
    {
        return $this->apiGet("/api/receipts/{$id}")->assertOk()->json();
    }

    /**
     * act body：operator=null = 整个键缺失（对照 ct operator 缺失/空串两形态）
     *
     * @return list<array<string, mixed>>
     */
    private function act(string $stage, string $receiptId, string $action, ?string $operator = 'tester-op', ?string $reason = null): array
    {
        $payload = ['ids' => [$receiptId], 'action' => $action];
        if ($operator !== null) {
            $payload['operator'] = $operator;
        }
        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        return $this->apiPost("/api/receipts/{$stage}/act", $payload)->assertOk()->json();
    }

    private function lastHistoryAction(): ?string
    {
        $history = $this->apiGet("/api/receipts/{$this->receiptId}/history")->assertOk()->json();

        $last = $history[count($history) - 1];

        return isset($last['action']) ? (string) $last['action'] : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findInFilteredList(string $filter): ?array
    {
        $envelope = $this->apiGet("/api/receipts?filter={$filter}&pageSize=500")->assertOk()->json();
        foreach ($envelope['items'] as $item) {
            if (($item['id'] ?? null) === $this->receiptId) {
                return $item;
            }
        }

        return null;
    }

    private function advanceToArchived(string $id): void
    {
        foreach (['receiving', 'assigning', 'data-entry', 'review', 'approve', 'issuance', 'archived'] as $stage) {
            $this->act($stage, $id, 'submit');
        }
    }
}
