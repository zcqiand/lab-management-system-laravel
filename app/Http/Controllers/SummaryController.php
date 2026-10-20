<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\Summary\SummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M05 报告汇总 + 仪表盘（B4，2 端点）。tenant 从 JWT claim 取
 * （lab-springboot SummaryController 镜像 → rails summary_controller.rb 同构移植）。
 * summary 无单资源寻址（只有参数面），无分页信封——BusinessEnvelope 只用 queryParam。
 */
final class SummaryController extends Controller
{
    use BusinessEnvelope;

    // @impl M05.F01.I01 (book anchor xr-know-012)
    public function getReportSummary(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service($request)->reportSummary(
            $this->queryParam($request, 'categoryCode'),
            $this->queryParam($request, 'dateFrom'),
            $this->queryParam($request, 'dateTo'),
        ));
    }

    // stats 键集是 wire 形状（funnelByStage 的 "pending_collect" 是 openapi 生成名，
    // 不得被 camelCase 变换折叠成 pendingCollect）——直接 json 渲染，不走 renderCamel。
    // @impl M05.F01.I06 (book anchor xr-know-012)
    public function getDashboardStats(Request $request): JsonResponse
    {
        return response()->json($this->service($request)->dashboardStats());
    }

    private function service(Request $request): SummaryService
    {
        return new SummaryService($this->currentTenantId($request));
    }
}
