<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * inspection-dictionary 检测字典（object/parameter/specialty/standard + links）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag inspection-dictionary，28 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class InspectionDictionaryController extends Controller
{
    public function createObject(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createSpecialty(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteObject(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteSpecialty(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkObjectParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkObjectStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkSpecialtyObject(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkStandardParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listObjectParameterLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listObjectStandardLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listObjects(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listParameters(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listSpecialties(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listSpecialtyObjectLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listStandardParameterLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listStandards(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkObjectParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkObjectStandard(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkSpecialtyObject(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkStandardParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateObject(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateParameter(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateSpecialty(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateStandard(): JsonResponse
    {
        return $this->notImplemented();
    }
}
