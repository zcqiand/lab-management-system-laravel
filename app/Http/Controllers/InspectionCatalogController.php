<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * inspection-catalog 字典目录（brand/grade/model/spec，code 型 PK）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag inspection-catalog，16 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class InspectionCatalogController extends Controller
{
    public function createBrand(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createGrade(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createModel(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function createSpec(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteBrand(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteGrade(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteModel(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteSpec(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listBrands(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listGrades(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listModels(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listSpecs(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateBrand(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateGrade(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateModel(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateSpec(): JsonResponse
    {
        return $this->notImplemented();
    }
}
