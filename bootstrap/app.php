<?php

declare(strict_types=1);

use App\Exceptions\AccessDenied;
use App\Exceptions\InvalidCredentials;
use App\Exceptions\InvalidGrant;
use App\Exceptions\Jwt\TokenExpired;
use App\Exceptions\Jwt\TokenInvalid;
use App\Exceptions\MenusUnavailable;
use App\Exceptions\SaasIdpUnavailable;
use App\Exceptions\UpstreamUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Middleware\JwtGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

            // Swagger 文档面（REQ-2026-002，saas 同款镜像）：/ 302 → 壳页 →
            // shared 契约生成物（public/api-docs.yaml，gen-shared.sh 落盘——文件非目录，
            // 防遮蔽 /api-docs 路由）+ public/swagger-ui/5.34.0/ pinned 静态件（vendor
            // 入库，npmmirror 钉版下载），零手写注解零漂移。与 /health 同模式经 then:
            // 注册、不挂 web 组：web 组 StartSession 走 database driver，无 DB 面一律
            // 500（v0.2.0 首航红指纹：CI smoke 占位 env 连不上库/本地无 sessions 表，
            // 表现为 /health 200 而 /api-docs 500）——文档面是基础设施端点，不依赖
            // session/DB。不进契约面/功能树（与 /health 同模式）。
            // bump swagger-ui-dist 时同 commit 换 public/swagger-ui/<ver>/ 并改本注释、
            // resources/views/docs/swagger.blade.php、ci.yml 探针三处版本引用。
            Route::get('/', fn (): RedirectResponse => redirect('/api-docs'));
            Route::view('/api-docs', 'docs.swagger');
            Route::get('/api-docs/openapi.yaml', function (): Response {
                // shared 契约生成物原样透传（gen-shared.sh 落盘），禁手编——漂移在 regen 即同步
                $path = public_path('api-docs.yaml');
                abort_unless(is_file($path), 404);

                return response(file_get_contents($path), Response::HTTP_OK, ['Content-Type' => 'application/yaml']);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // jwt.guard（rails concerns/jwt_guard.rb 镜像）：保护面 115 条路由组挂载（Task 3
        // 钉死）。Task 4 起为真内核：Bearer JWT 验签（HS256 + iss + typ=access）→ 401
        // INVALID_CREDENTIALS / 身份 attributes 注入。
        $middleware->alias(['jwt.guard' => JwtGuard::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 错误体 {code,message} 映射——rails application_controller rescue_from 表逐行
        // 镜像（镜像 springboot GlobalExceptionHandler，ADR-0020 错误契约）。
        // 映射表外异常返回 null 走框架默认处理（不吞）。
        $exceptions->render(function (Throwable $e, Request $request): ?Response {
            // JwtGuard 不经异常链（直接回 401 响应）；此口子接 decode_bearer!（oauth 链
            // 手动 decode，rails rescue JWT::* → InvalidCredentials 镜像）。
            if ($e instanceof TokenExpired) {
                return response()->json(['code' => 'INVALID_CREDENTIALS', 'message' => 'token expired'], 401);
            }
            if ($e instanceof TokenInvalid || $e instanceof InvalidCredentials) {
                return response()->json(
                    ['code' => 'INVALID_CREDENTIALS', 'message' => $e->getMessage() ?: 'invalid credentials'],
                    401
                );
            }

            if ($e instanceof AccessDenied) {
                return response()->json(['code' => 'FORBIDDEN', 'message' => $e->getMessage() ?: 'access denied'], 403);
            }

            // —— lab 认证域特有映射（lab rails application_controller:49-63 镜像）——
            if ($e instanceof MenusUnavailable) {
                return response()->json(['code' => 'MENUS_UNAVAILABLE', 'message' => $e->getMessage()], 503);
            }
            if ($e instanceof SaasIdpUnavailable) {
                return response()->json(['code' => 'SAAS_UNAVAILABLE', 'message' => $e->getMessage()], 502);
            }
            if ($e instanceof InvalidGrant) {
                return response()->json(['code' => 'INVALID_GRANT', 'message' => $e->getMessage()], 400);
            }
            if ($e instanceof UpstreamUnavailable) {
                return response()->json(['code' => 'SAAS_UPSTREAM_ERROR', 'message' => $e->getMessage()], 502);
            }

            // UUID 参数甄别（镜像 springboot UUID.fromString，rails RecordNotFound rescue
            // 同形）：路径段声称 uuid 但格式非法 → 400（IAE 语义）；合法格式但查无 → 404。
            // Laravel 在 render callbacks 之前经 prepareException 把 ModelNotFoundException
            // 包成 NotFoundHttpException，故此处认 previous（裸 NotFoundHttpException=路由
            // 404，不属本映射，走框架默认）。
            if ($e instanceof NotFoundHttpException && $e->getPrevious() instanceof ModelNotFoundException) {
                $bad = Controller::badUuidParam($request);
                if ($bad !== null) {
                    return response()->json(['code' => 'BAD_REQUEST', 'message' => "malformed uuid: {$bad}"], 400);
                }

                return response()->json(
                    ['code' => 'NOT_FOUND', 'message' => $e->getMessage() ?: 'resource not found'],
                    404
                );
            }

            // ArgumentError → 400（分页参数校验等）
            if ($e instanceof InvalidArgumentException) {
                return response()->json(
                    ['code' => 'BAD_REQUEST', 'message' => $e->getMessage() ?: 'invalid argument'],
                    400
                );
            }

            // 完整性违反（Unique 23505 / NotNull 23502 / FK 23503 / 23000）→ 400
            // "constraint violation: …"（rails RecordInvalid/InvalidForeignKey/
            // NotNullViolation/RecordNotUnique 同组镜像；其余 QueryException 不吞，
            // 走框架默认 500）
            if ($e instanceof QueryException
                && preg_match('/SQLSTATE\[(23000|23502|23503|23505)\]/', $e->getMessage()) === 1) {
                return response()->json(
                    ['code' => 'BAD_REQUEST', 'message' => "constraint violation: {$e->getMessage()}"],
                    400
                );
            }

            // ParameterMissing / JSON.parse 失败镜像（HttpMessageNotReadable → 400）
            if ($e instanceof BadRequestHttpException) {
                return response()->json(['code' => 'BAD_REQUEST', 'message' => 'malformed request body'], 400);
            }

            return null;
        });
    })
    ->create();
