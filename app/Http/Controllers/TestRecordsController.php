<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * test-records 检测记录（M09，含 verdict 裁定）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag test-records，6 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class TestRecordsController extends Controller
{
    public function createTestRecord(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteTestRecord(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getTestRecord(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listTestRecords(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function setVerdict(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateTestRecord(): JsonResponse
    {
        return $this->notImplemented();
    }
}
