<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * 路由壳期统一 501 占位（Task 3）。
     *
     * 业务实装任务逐 action 替换为真实现；501 让「已注册未实装」在契约测试与
     * contract-test live 两面都显形，不冒充 200 空成功。
     */
    protected function notImplemented(): JsonResponse
    {
        return response()->json(['code' => 'NOT_IMPLEMENTED', 'message' => 'not implemented yet'], 501);
    }
}
