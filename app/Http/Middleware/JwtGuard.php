<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Jwt\TokenExpired;
use App\Exceptions\Jwt\TokenInvalid;
use App\Services\Auth\JwtService;
use App\Services\Auth\UserDirectory;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// lab 家族鉴权门（rails app/controllers/concerns/jwt_guard.rb 的 Laravel 镜像）：
// 全业务端点 Bearer JWT（typ=access，iss=JWT_ISSUER）；匿名放行面只有
// auth login/native-login/refresh/sso/** + /health。缺/坏/过期 token → 401
// INVALID_CREDENTIALS——身份字段缺失禁兜底（ADR-0019 / suite-hard-rules §3）。
//
// 与 saas TenantGuard 的有意差异：lab 全路由无 tenant 路径段，**不做 verifyPathTenant**；
// claim tenant_id 缺省回退 UserDirectory::defaultTenant()（TENANT-001 硬编码，禁
// LAB_SAAS_DEFAULT_TENANT_ID env 兜底——业务租户域与 saas 服务账号域分离，2026-09-29
// live 实锤）。
final class JwtGuard
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : $header;
        if ($token === '' || $token === $header) {
            return self::unauthorized('missing bearer token');
        }

        try {
            // 只放行 typ=access：refresh token 打保护面同样 401（rails verify! typ 镜像）。
            $payload = $this->jwt->verify($token);
        } catch (TokenExpired) {
            return self::unauthorized('token expired');
        } catch (TokenInvalid $e) {
            return self::unauthorized("invalid token: {$e->getMessage()}");
        }

        // 身份缝（Task 5-13 消费约定）：控制器经
        //   $request->attributes->get('currentUserId')   ← JWT sub
        //   $request->attributes->get('currentTenantId') ← JWT tenant_id claim，
        // 缺省回退目录默认租户；查询必须用它过滤，禁全表泄露、禁 demo 字面量兜底。
        $claimTenantId = $payload['tenant_id'] ?? null;
        $request->attributes->set('currentUserId', $payload['sub'] ?? null);
        $request->attributes->set('currentTenantId', is_string($claimTenantId) && $claimTenantId !== ''
            ? $claimTenantId
            : (new UserDirectory)->defaultTenant()['tenant_id']);

        return $next($request);
    }

    private static function unauthorized(string $message): JsonResponse
    {
        return response()
            ->json(['code' => 'INVALID_CREDENTIALS', 'message' => $message], 401)
            ->header('WWW-Authenticate', 'Bearer realm="api"');
    }
}
