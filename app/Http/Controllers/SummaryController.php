<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * summary 工作台统计路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag summary，2 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class SummaryController extends Controller
{
    public function getDashboardStats(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getReportSummary(): JsonResponse
    {
        return $this->notImplemented();
    }
}
