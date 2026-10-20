<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * calculation-methods 计算方法（复合 PK object×parameter）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag calculation-methods，5 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class CalculationMethodsController extends Controller
{
    public function createCalculationMethod(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteCalculationMethod(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getCalculationMethod(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listCalculationMethods(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateCalculationMethod(): JsonResponse
    {
        return $this->notImplemented();
    }
}
