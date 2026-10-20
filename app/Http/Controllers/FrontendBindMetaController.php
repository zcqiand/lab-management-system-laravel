<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * frontend-bind-meta 前端绑定快照（内部面；契约面 1:1 对
 * generated/api_manifest.json，tag frontend-bind-meta，1 条）。
 *
 * GET /api/_frontend-bind/snapshot —— 生成器 DTO（registry/authContext/tokenKeys）存在但
 * lab-springboot 无 @RestController 实现；ct 也不测本端点。rails 1:1 镜像：action 存在
 * （route-parity 要求）。路由挂 jwt.guard 组：springboot 该路径不在 permitAll 名单，
 * Security 过滤链先于 404 handler → 匿名 401（2026-09-29 review Important 补 include）；
 * 带合法 Bearer → 404 NOT_FOUND。
 */
final class FrontendBindMetaController extends Controller
{
    public function getFrontendBindSnapshot(): JsonResponse
    {
        return $this->renderError(404, 'NOT_FOUND', 'resource not found');
    }
}
