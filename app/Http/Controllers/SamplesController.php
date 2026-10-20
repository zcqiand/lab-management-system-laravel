<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * samples 样品（M08，含 ext 补录）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag samples，6 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class SamplesController extends Controller
{
    public function createSample(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteSample(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getSample(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listSamples(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateSample(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateSampleExt(): JsonResponse
    {
        return $this->notImplemented();
    }
}
