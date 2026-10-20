<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * contracts 委托合同（M02，tenant 隔离）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag contracts，5 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class ContractsController extends Controller
{
    public function createContract(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteContract(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getContract(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listContracts(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateContract(): JsonResponse
    {
        return $this->notImplemented();
    }
}
