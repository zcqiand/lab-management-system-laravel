<?php

declare(strict_types=1);

use App\Http\Middleware\JwtGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        // 契约路径自带 /api 前缀（api_manifest.json 1:1），apiPrefix 留空防 /api/api 叠加
        // （saas laravel 同款）。
        apiPrefix: '',
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            // 家族探活契约形状：GET /health → {"ok":true,"mode":"..."}（ktor Application.kt 同款）。
            // 经 then: 注册、不挂 web 中间件组——探活是基础设施，不走 session/cookie，无 APP_KEY 依赖。
            // /health 不进契约面（RouteParityTest 唯一 allowlist）；业务 API 一律走 routes/api.php。
            Route::get('/health', fn (): JsonResponse => response()->json(['ok' => true, 'mode' => 'laravel']));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // jwt.guard：保护面 115 条路由组挂载。真 JWT 验签内核（HS256 + JWT_ISSUER 校验
        // + 401 INVALID_CREDENTIALS 形状）在 auth 实装任务替换 JwtGuard 的 handle 体——
        // alias 与挂载面已随路由壳钉死，实装只换内核不动路由面。
        $middleware->alias(['jwt.guard' => JwtGuard::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
