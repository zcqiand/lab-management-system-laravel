<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * auth 登录/会话（匿名 5 + 保护 5）路由壳（Task 3）：契约面 1:1 对 generated/api_manifest.json
 * （tag auth，10 条）。action 一律 501 占位——业务实装在后续 tag 任务
 * 逐个替换本方法体，签名（路径参数）已按契约钉死。
 */
final class AuthController extends Controller
{
    public function getCurrentUser(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getMenus(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function getPermissions(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function login(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function logout(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function nativeLogin(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function refresh(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function ssoAuthorize(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function ssoCallback(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function switchTenant(): JsonResponse
    {
        return $this->notImplemented();
    }
}
