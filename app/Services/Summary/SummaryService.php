<?php

declare(strict_types=1);

namespace App\Services\Summary;

use App\Models\Contract;
use App\Models\Sample;
use App\Models\SampleReceipt;
use Illuminate\Database\Eloquent\Builder;

// M05 报告汇总 + 仪表盘统计（lab-springboot SummaryService 镜像 → rails
// app/services/summary/summary_service.rb Laravel 同构移植）。按 tenant +
// categoryCode（'ALL' = 不过滤）过滤 sample_receipts，commissionDate 字典序当
// 日期序（text 列 ISO-8601 串比较）；stats = 3 总数 + 3 桶状态 + 任务计数 +
// 4 段扩展（M05.F01.I03/I04）。stats 键集是 wire 形状（funnelByStage 的
// "pending_collect" 是 openapi 生成名）——控制器直接 json 渲染不走 renderCamel。
final class SummaryService
{
    use MaterialRates;

    private const CATEGORY_ALL = 'ALL';

    private const COLUMNS = [
        ['key' => 'commissionCode', 'label' => '委托编号'],
        ['key' => 'categoryCode', 'label' => '报告类别'],
        ['key' => 'projectName', 'label' => '工程名称'],
        ['key' => 'flowStatus', 'label' => '流程状态'],
        ['key' => 'result', 'label' => '结论'],
        ['key' => 'reportCode', 'label' => '报告编号'],
    ];

    public function __construct(private readonly string $tenantId) {}

    /**
     * @return array{summary_name: string, columns: list<array{key: string, label: string}>, rows: list<array<string, string>>}
     */
    public function reportSummary(?string $categoryCode, ?string $dateFrom, ?string $dateTo): array
    {
        $rows = array_map(fn (SampleReceipt $r): array => $this->renderRow($r),
            $this->summaryScope($categoryCode, $dateFrom, $dateTo)->get()->all());

        return [
            'summary_name' => sprintf('报告汇总（%s）', $this->categoryOf($categoryCode)),
            'columns' => self::COLUMNS,
            'rows' => $rows,
        ];
    }

    /**
     * 键序逐字照 rails dashboard_stats 字面量（wire 形状）。
     *
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $receipts = $this->summaryScope(self::CATEGORY_ALL, null, null)->get()->all();
        $contractCount = Contract::query()->where('tenant_id', $this->tenantId)->count();
        $sampleCount = Sample::query()->where('tenant_id', $this->tenantId)->count();
        // rails group_by(&:flow_status) 同构：flow_status → rows
        $counting = [];
        foreach ($receipts as $receipt) {
            $counting[$receipt->flow_status][] = $receipt;
        }

        return [
            'contractCount' => $contractCount,
            'receiptCount' => count($receipts),
            'sampleCount' => $sampleCount,
            'reportCountByStatus' => $this->reportCountByStatus($counting),
            'pendingTaskCount' => $this->bucket($counting, 'task_assignment', 'data_entry', 'review'),
            'todayTestCount' => $this->todayTestCount($receipts),
            'qualifiedRateByMaterial' => $this->qualifiedRateByMaterial($receipts),
            'reportOutputByStatus' => $this->reportOutputByStatus($receipts, $counting),
            'funnelByStage' => $this->funnelByStage($counting),
        ];
    }

    private function categoryOf(?string $categoryCode): string
    {
        return $categoryCode === null || $categoryCode === ''
            ? self::CATEGORY_ALL : $categoryCode;
    }

    // 汇总查询：tenant + 可选 categoryCode（ALL = 不过滤）+ 可选 commissionDate 前后界
    // （YYYY-MM-DD 字典序 = 日期序，null 视作无界）；排序 commissionDate DESC, commissionCode
    /**
     * @return Builder<SampleReceipt>
     */
    private function summaryScope(?string $categoryCode, ?string $dateFrom, ?string $dateTo): Builder
    {
        $query = SampleReceipt::query()->where('tenant_id', $this->tenantId);
        $cat = $this->categoryOf($categoryCode);
        if ($cat !== self::CATEGORY_ALL) {
            $query->where('category_code', $cat);
        }
        if ($dateFrom !== null) {
            $query->where('commission_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->where('commission_date', '<=', $dateTo);
        }

        return $query->orderByDesc('commission_date')->orderBy('commission_code');
    }

    // 3 桶：draft = 前 3 阶段 / reviewing = review+approval / issued = issuance+archived
    /**
     * @param  array<string, list<SampleReceipt>>  $counting
     * @return array{draft: int, reviewing: int, issued: int}
     */
    private function reportCountByStatus(array $counting): array
    {
        return [
            'draft' => $this->bucket($counting, 'receiving', 'task_assignment', 'data_entry'),
            'reviewing' => $this->bucket($counting, 'review', 'approval'),
            'issued' => $this->bucket($counting, 'issuance', 'archived'),
        ];
    }

    /**
     * @param  array<string, list<SampleReceipt>>  $counting
     */
    private function bucket(array $counting, string ...$stages): int
    {
        $sum = 0;
        foreach ($stages as $stage) {
            $sum += count($counting[$stage] ?? []);
        }

        return $sum;
    }

    // 今日试验：created_at 或 test_start_date 以今天开头（字符串前缀，镜像 Java
    // startsWith / rails start_with?；库存 UTC ISO 串，「今天」取 UTC 日历日与
    // Time::nowIso 同源）
    /**
     * @param  list<SampleReceipt>  $receipts
     */
    private function todayTestCount(array $receipts): int
    {
        $today = gmdate('Y-m-d');
        $count = 0;
        foreach ($receipts as $receipt) {
            if (str_starts_with((string) $receipt->created_at, $today)
                || str_starts_with((string) $receipt->test_start_date, $today)) {
                $count += 1;
            }
        }

        return $count;
    }

    /**
     * @param  list<SampleReceipt>  $receipts
     * @param  array<string, list<SampleReceipt>>  $counting
     * @return array{generated: int, pending: int, issued: int}
     */
    private function reportOutputByStatus(array $receipts, array $counting): array
    {
        $generated = 0;
        foreach ($receipts as $receipt) {
            if ($receipt->report_code !== null) {
                $generated += 1;
            }
        }

        return [
            'generated' => $generated,
            'pending' => $this->bucket($counting, 'review', 'approval'),
            'issued' => $this->bucket($counting, 'issuance', 'archived'),
        ];
    }

    // 6 段任务漏斗：testing/reporting 按 data_entry 是否已有 report_code 拆分
    // （rails funnel_by_stage 首参 receipts 未消费——_receipts，此处只吃 counting）
    /**
     * @param  array<string, list<SampleReceipt>>  $counting
     * @return array{pending_collect: int, received: int, testing: int, reporting: int, reviewing: int, issued: int}
     */
    private function funnelByStage(array $counting): array
    {
        $testing = 0;
        $reporting = 0;
        foreach ($counting['data_entry'] ?? [] as $receipt) {
            if ($receipt->report_code === null) {
                $testing += 1;
            } else {
                $reporting += 1;
            }
        }

        return [
            'pending_collect' => $this->bucket($counting, 'receiving'),
            'received' => $this->bucket($counting, 'task_assignment'),
            'testing' => $testing,
            'reporting' => $reporting,
            'reviewing' => $this->bucket($counting, 'review', 'approval'),
            'issued' => $this->bucket($counting, 'issuance', 'archived'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function renderRow(SampleReceipt $r): array
    {
        return [
            'commissionCode' => (string) $r->commission_code,
            'categoryCode' => (string) $r->category_code,
            'projectName' => (string) $r->project_name,
            'flowStatus' => (string) $r->flow_status,
            'result' => (string) $r->result,
            'reportCode' => (string) $r->report_code,
        ];
    }
}
