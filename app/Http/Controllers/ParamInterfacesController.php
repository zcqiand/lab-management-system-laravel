<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * param-interfaces 参数接口 + links路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag param-interfaces，8 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class ParamInterfacesController extends Controller
{
    public function createParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function deleteParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function linkParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listParamInterfaceLinks(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function listParamInterfaces(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function unlinkParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function updateParamInterface(): JsonResponse
    {
        return $this->notImplemented();
    }
}
