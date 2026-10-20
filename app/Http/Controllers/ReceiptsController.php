<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * receipts 委托接收/流程动作（M06 七态流转）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag receipts，14 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class ReceiptsController extends Controller
{
    public function actFlowApprove(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowArchived(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowAssigning(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowDataEntry(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowIssuance(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowReceiving(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function actFlowReview(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function assignTask(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createReceipt(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteReceipt(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getReceipt(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getReceiptHistory(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listReceipts(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateReceipt(): JsonResponse
    {
        return $this->notImplemented();
    }
}
