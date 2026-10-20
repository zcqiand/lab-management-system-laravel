<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * report-names 报告名称 + links（object/parameter/standard）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag report-names，14 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class ReportNamesController extends Controller
{
    public function createReportName(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteReportName(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getReportName(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkObjectReportName(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkReportNameParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkReportNameStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listObjectReportNameLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listReportNameParameterLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listReportNameStandardLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listReportNames(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkObjectReportName(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkReportNameParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkReportNameStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateReportName(): JsonResponse
    {
        return $this->notImplemented();
    }
}
