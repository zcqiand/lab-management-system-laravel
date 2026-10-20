<?php

declare(strict_types=1);

namespace App\Services\Summary;

use App\Models\InspectionReportName;
use App\Models\SampleReceipt;
use Illuminate\Support\Collection as SupportCollection;

// 仪表盘「按材料类型合格率」段（lab-springboot SummaryService.qualifiedRateByMaterial
// 镜像 → rails app/services/summary/material_rates.rb Laravel 同构移植）：
// 码表 summaryName 关键词归类 concrete/rebar/sand，rate 保留三位小数。
trait MaterialRates
{
    // summaryName → 材料类型映射（与 msw handlers-extra 同款语义，LinkedHashMap 序：
    // 命中判定按 concrete → rebar → sand 顺序短路）
    private const MATERIAL_KEYWORDS = [
        'concrete' => ['混凝土', '水泥'],
        'rebar' => ['钢筋', '钢材', '焊接', '机械连接', '连接'],
        'sand' => ['砂', '碎（卵）石', '轻集料', '颗粒级配'],
    ];

    /**
     * 按材料类型合格率：码表全量预载（summaryName 关键词归类），rate 三位小数
     * （(pass*1000/total).round / 1000.0，Ruby Float#round 逐语义）。
     *
     * @param  list<SampleReceipt>  $receipts
     * @return array<string, array{total: int, pass: int, rate: float}>
     */
    protected function qualifiedRateByMaterial(array $receipts): array
    {
        $summaryNameByCode = InspectionReportName::query()
            ->pluck('summary_name', 'code');
        $total = ['concrete' => 0, 'rebar' => 0, 'sand' => 0];
        $pass = ['concrete' => 0, 'rebar' => 0, 'sand' => 0];

        foreach ($receipts as $receipt) {
            $mat = $this->materialOf($receipt->category_code, $summaryNameByCode);
            if ($mat === null) {
                continue;
            }
            $total[$mat] += 1;
            if ($receipt->result === 'pass') {
                $pass[$mat] += 1;
            }
        }

        $rates = [];
        foreach ($total as $mat => $t) {
            $rate = $t > 0 ? (int) round($pass[$mat] * 1000.0 / $t) / 1000.0 : 0.0;
            $rates[$mat] = ['total' => $t, 'pass' => $pass[$mat], 'rate' => $rate];
        }

        return $rates;
    }

    /**
     * category_code → 码表 summaryName 关键词归类；无码 / 无命中 → null（不入桶）。
     *
     * @param  SupportCollection<string, string|null>  $summaryNameByCode
     */
    private function materialOf(
        ?string $categoryCode,
        SupportCollection $summaryNameByCode,
    ): ?string {
        if ($categoryCode === null) {
            return null;
        }

        // rails summary_name.to_s：码表 summary_name 可空 → null 折 ''（无命中）
        $summaryName = (string) ($summaryNameByCode->get($categoryCode) ?? '');
        foreach (self::MATERIAL_KEYWORDS as $mat => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($summaryName, $keyword)) {
                    return $mat;
                }
            }
        }

        return null;
    }
}
