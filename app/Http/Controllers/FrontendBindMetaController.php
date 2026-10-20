<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * frontend-bind-meta 前端绑定快照（内部面）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag frontend-bind-meta，1 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class FrontendBindMetaController extends Controller
{
    public function getFrontendBindSnapshot(): JsonResponse
    {
        return $this->notImplemented();
    }
}
