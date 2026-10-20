<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * technical-requirements 技术要求（3 列复合 PK）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag technical-requirements，5 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class TechnicalRequirementsController extends Controller
{
    public function createTechnicalRequirement(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteTechnicalRequirement(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getTechnicalRequirement(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listTechnicalRequirements(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateTechnicalRequirement(): JsonResponse
    {
        return $this->notImplemented();
    }
}
