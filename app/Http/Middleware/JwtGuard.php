<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * jwt.guard 占位内核（Task 3 路由壳批次）：透传。
 *
 * 真 JWT 验签（HS256 + JWT_ISSUER 校验 + 401 INVALID_CREDENTIALS 形状，镜像
 * rails app/lib/auth/token_verifier.rb）在 auth 实装任务替换本类 handle 体——
 * alias 与挂载面已随路由壳钉死，实装只换内核不动路由。
 */
final class JwtGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
