<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\InspectionReportName;
use App\Models\SampleReceipt;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M05 报告汇总 + 仪表盘统计集成测试（行为真源 = lab-springboot SummaryService；
// lab-ct summary.test.ts：summaryName/columns/rows + DashboardStats 9 段必填面；
// 镜像 lab rails test/integration/summary_api_test.rb 全 8 用例，不加戏）。
// 真库链（lab_test）事务回滚；token 直签；数据建模直写（rails create_*_ar 同位）。
final class SummaryApiTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // rails BusinessApiTest 口径：token 缺省租户 = env LAB_SAAS_DEFAULT_TENANT_ID
    public function defaultAuthTenantId(): string
    {
        return $this->tenantId();
    }

    private Contract $contract;

    private string $reportNameCode = '';

    private SampleReceipt $passIssued;

    // rails setup 镜像：合同 + 报告名（summary_name 含「混凝土」）+ 两张自建单
    // （fresh_receiving 只吃 todayTestCount ≥ 1 断言，不留引用）
    protected function setUp(): void
    {
        parent::setUp();
        $this->contract = $this->createContract();
        $this->reportNameCode = $this->createReportName();
        $this->passIssued = $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'issuance', 'result' => 'pass',
            'report_code' => 'RP-T-1', 'project_name' => '工程甲',
        ]);
        $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'receiving', 'result' => null,
            'test_start_date' => gmdate('Y-m-d'), 'project_name' => '工程乙',
        ]);
    }

    #[Test]
    public function missing_bearer_token_is_401(): void
    {
        $this->getJson('/api/summary')->assertUnauthorized();
    }

    #[Test]
    public function report_summary_face_and_row_rendering(): void
    {
        $data = $this->apiGet('/api/summary')->assertOk()->json();

        $this->assertSame('报告汇总（ALL）', $data['summaryName']);
        $this->assertSame(
            ['commissionCode', 'categoryCode', 'projectName', 'flowStatus', 'result', 'reportCode'],
            array_column($data['columns'], 'key')
        );
        $this->assertSame(
            ['委托编号', '报告类别', '工程名称', '流程状态', '结论', '报告编号'],
            array_column($data['columns'], 'label')
        );

        $rows = array_column($data['rows'], null, 'commissionCode');
        $this->assertArrayHasKey($this->passIssued->commission_code, $rows, '汇总行应含自建单');
        $row = $rows[$this->passIssued->commission_code];
        $this->assertSame('issuance', $row['flowStatus']);
        $this->assertSame('pass', $row['result']);
        $this->assertSame('RP-T-1', $row['reportCode']);
        $this->assertSame('工程甲', $row['projectName']);
    }

    #[Test]
    public function category_code_filter_and_empty_string_falls_back_to_all(): void
    {
        $byCategory = $this->apiGet("/api/summary?categoryCode={$this->reportNameCode}")
            ->assertOk()->json();
        $this->assertSame("报告汇总（{$this->reportNameCode}）", $byCategory['summaryName']);
        $this->assertContains(
            $this->passIssued->commission_code,
            array_column($byCategory['rows'], 'commissionCode')
        );

        $noHit = $this->apiGet('/api/summary?categoryCode=RN-NO-SUCH')->assertOk()->json();
        $this->assertSame([], $noHit['rows']);
    }

    #[Test]
    public function date_from_date_to_use_commission_date_lexicographic_compare(): void
    {
        // commission_date=2026-09-20（text 列字典序 = 日期序）：下界 2026-09-21 排除
        $late = $this->apiGet('/api/summary?dateFrom=2026-09-21')->assertOk()->json();
        $this->assertNotContains(
            $this->passIssued->commission_code,
            array_column($late['rows'], 'commissionCode')
        );

        // 前后界夹住 2026-09-20 → 命中
        $window = $this->apiGet('/api/summary?dateFrom=2026-09-01&dateTo=2026-09-30')
            ->assertOk()->json();
        $this->assertContains(
            $this->passIssued->commission_code,
            array_column($window['rows'], 'commissionCode')
        );
    }

    #[Test]
    public function stats_nine_segment_face_and_counts_converge(): void
    {
        $before = $this->apiGet('/api/summary/stats')->assertOk()->json();

        $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'archived', 'result' => 'pass', 'report_code' => 'RP-T-2',
        ]);

        $after = $this->apiGet('/api/summary/stats')->assertOk()->json();

        $this->assertStatsFace($after);

        $this->assertSame($before['receiptCount'] + 1, $after['receiptCount']);
        $this->assertSame(
            $before['reportCountByStatus']['issued'] + 1,
            $after['reportCountByStatus']['issued']
        );
        $this->assertSame(
            $before['funnelByStage']['issued'] + 1,
            $after['funnelByStage']['issued']
        );
        $this->assertSame(
            $before['qualifiedRateByMaterial']['concrete']['pass'] + 1,
            $after['qualifiedRateByMaterial']['concrete']['pass'],
            '材料合格率 pass 只计 result=pass 行'
        );
        $this->assertGreaterThanOrEqual(
            1,
            $after['todayTestCount'],
            'todayTestCount 应计入 test_start_date=今天 的单'
        );
    }

    #[Test]
    public function stats_tenant_scoping_other_tenant_sees_nothing(): void
    {
        $stats = $this->apiGet('/api/summary/stats', $this->otherTenantId())->assertOk()->json();

        $this->assertSame(0, $stats['receiptCount']);
        $this->assertSame(0, $stats['reportCountByStatus']['issued']);
    }

    #[Test]
    public function m05_f01_i03_core_metrics_drive_four_segments_with_one_receipt(): void
    {
        $before = $this->apiGet('/api/summary/stats')->assertOk()->json();

        // 一张单同时驱动四段：issuance + result=pass + report_code + created_at=今天
        $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'issuance', 'result' => 'pass', 'report_code' => 'RP-T-9',
        ]);

        $after = $this->apiGet('/api/summary/stats')->assertOk()->json();

        $this->assertSame(
            $before['todayTestCount'] + 1,
            $after['todayTestCount'],
            '新建单 created_at=今天 → 今日试验数 +1'
        );
        $this->assertSame(
            $before['reportOutputByStatus']['generated'] + 1,
            $after['reportOutputByStatus']['generated'],
            '有 report_code → 已生成 +1'
        );
        $this->assertSame(
            $before['reportOutputByStatus']['issued'] + 1,
            $after['reportOutputByStatus']['issued'],
            'issuance → 已签发 +1'
        );
        $this->assertSame(
            $before['qualifiedRateByMaterial']['concrete']['total'] + 1,
            $after['qualifiedRateByMaterial']['concrete']['total'],
            '码表 summaryName 含「混凝土」→ concrete 桶 total +1'
        );
        $this->assertSame(
            $before['qualifiedRateByMaterial']['concrete']['pass'] + 1,
            $after['qualifiedRateByMaterial']['concrete']['pass'],
            'result=pass → concrete 桶 pass +1'
        );
    }

    #[Test]
    public function m05_f01_i04_funnel_splits_data_entry_by_report_code(): void
    {
        $before = $this->apiGet('/api/summary/stats')->assertOk()->json()['funnelByStage'];

        // 六种状态各一张 → 六段各 +1（data_entry 有/无 report_code 分 reporting/testing）
        $this->createReceipt($this->reportNameCode, ['flow_status' => 'receiving']);
        $this->createReceipt($this->reportNameCode, ['flow_status' => 'task_assignment']);
        $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'data_entry', 'report_code' => null,
        ]);
        $this->createReceipt($this->reportNameCode, [
            'flow_status' => 'data_entry', 'report_code' => 'RP-T-9',
        ]);
        $this->createReceipt($this->reportNameCode, ['flow_status' => 'review']);
        $this->createReceipt($this->reportNameCode, ['flow_status' => 'issuance']);

        $funnel = $this->apiGet('/api/summary/stats')->assertOk()->json()['funnelByStage'];

        $expect = [
            'pending_collect' => 'receiving', 'received' => 'task_assignment',
            'testing' => 'data_entry 无 report_code', 'reporting' => 'data_entry 有 report_code',
            'reviewing' => 'review', 'issued' => 'issuance',
        ];
        foreach ($expect as $stage => $why) {
            $this->assertSame(
                $before[$stage] + 1,
                $funnel[$stage],
                "{$stage} 应 +1（{$why}）"
            );
        }
    }

    // ==== 助手（rails business_helpers create_*_ar 同位） ====

    /**
     * DashboardStats 9 段必填面（M05.F01.I03/I04）。
     *
     * @param  array<string, mixed>  $stats
     */
    private function assertStatsFace(array $stats): void
    {
        foreach (['contractCount', 'receiptCount', 'sampleCount', 'reportCountByStatus',
            'pendingTaskCount', 'todayTestCount', 'qualifiedRateByMaterial',
            'reportOutputByStatus', 'funnelByStage', ] as $key) {
            $this->assertArrayHasKey($key, $stats, "缺 {$key}");
        }
        foreach (['draft', 'reviewing', 'issued'] as $key) {
            $this->assertArrayHasKey($key, $stats['reportCountByStatus']);
        }
        foreach (['generated', 'pending', 'issued'] as $key) {
            $this->assertArrayHasKey($key, $stats['reportOutputByStatus']);
        }
        foreach (['concrete', 'rebar', 'sand'] as $mat) {
            foreach (['total', 'pass', 'rate'] as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $stats['qualifiedRateByMaterial'][$mat],
                    "qualifiedRateByMaterial.{$mat} 缺 {$key}"
                );
            }
        }
        foreach (['pending_collect', 'received', 'testing', 'reporting', 'reviewing', 'issued'] as $key) {
            $this->assertArrayHasKey($key, $stats['funnelByStage'], "funnelByStage 缺 {$key}");
        }
    }

    private function createContract(): Contract
    {
        $now = Time::nowIso();
        $contract = Contract::query()->create([
            'id' => 'C-'.Str::uuid()->toString(), 'tenant_id' => $this->tenantId(),
            'contract_code' => $this->uniqCode('CT-T'), 'client_unit' => 'unit',
            'project_name' => 'proj', 'construction_unit' => 'cons',
            'witness_unit' => 'wu', 'witness' => 'w', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        return $contract;
    }

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
     * rails create_receipt_ar 镜像：默认 receiving + result 空串 + commission_date
     * 2026-09-20，attrs 覆写任意列。
     *
     * @param  array<string, mixed>  $attrs
     */
    private function createReceipt(string $categoryCode, array $attrs = []): SampleReceipt
    {
        $now = Time::nowIso();

        return SampleReceipt::query()->create(array_merge([
            'id' => 'R-'.Str::uuid()->toString(), 'tenant_id' => $this->tenantId(),
            'contract_id' => $this->contract->id,
            'commission_code' => $this->uniqCode('WT-T'), 'commission_date' => '2026-09-20',
            'category_code' => $categoryCode, 'received_by' => 'alice',
            'sample_source' => 'client', 'test_category' => 'concrete',
            'flow_status' => 'receiving', 'flow_history' => [], 'result' => '',
            'created_at' => $now, 'updated_at' => $now,
        ], $attrs));
    }
}
