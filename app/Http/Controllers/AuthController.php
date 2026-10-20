<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InvalidCredentials;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// M00.F01/F02 + M01.F04/F05 认证域（薄层，rails auth_controller.rb 镜像）：claims
// 从 token 取，业务在 AuthService。匿名放行面（lab-springboot SecurityConfig 镜像）：
// login / native-login / refresh / sso/**（routes/api.php 钉死）；logout 与
// me/menus/permissions/switch-tenant 均需 Bearer（jwt.guard 组挂载）。
final class AuthController extends Controller
{
    // @impl M00.F01.I01 (book anchor xr-know-012)
    public function getCurrentUser(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->me($this->claims($request)));
    }

    public function getMenus(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->menus($this->claims($request)));
    }

    public function getPermissions(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->permissions());
    }

    // @impl M01.F05.I01 (book anchor xr-know-012)
    public function login(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->login($this->body($request)));
    }

    // M01.F05.I06 原生登录：非浏览器密码通道，校验与签发口径与 login 同源
    public function nativeLogin(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->login($this->body($request)));
    }

    // @impl M01.F05.I05 (book anchor xr-know-012)
    public function logout(Request $request): JsonResponse
    {
        $this->service()->logout($this->body($request));

        return response()->json()->setStatusCode(204);
    }

    public function refresh(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->refresh($this->body($request)));
    }

    // M01.F05.I02 — 返回 {authorizeUrl, state} 跳板（2026-08-29 收敛，服务端不预拿 code）。
    // query 参数名 snake_case redirect_uri（OAuth RFC 惯例，springboot AuthApi
    // @RequestParam("redirect_uri") 镜像；驼峰 redirectUri 不认）。
    public function ssoAuthorize(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->ssoAuthorize(
            $request->query('redirect_uri'),
            $request->query('state')
        ));
    }

    // M01.F05.I03 — code 换 token；state 校验已在前端回跳时完成
    public function ssoCallback(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->ssoCallback($this->body($request)));
    }

    // @impl M00.F02.I01 (book anchor xr-know-012)
    public function switchTenant(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service()->switchTenant($this->claims($request), $this->body($request)));
    }

    private function service(): AuthService
    {
        return AuthService::instance();
    }

    // 当前会话 raw claims（rails current_claims 镜像）：JwtGuard 注入的
    // currentTenantId attribute 是回退后值（缺 claim → TENANT-001），而 me 的
    // currentTenantId 优先级必须用 raw claim（无 claim → 快照首项/目录默认租户，
    // 2026-10-11 live 回归语义）——故此处对 Bearer token 重验取完整 payload。
    // token 已过 JwtGuard 验签，本次 verify 不会抛。
    /**
     * @return array<string, mixed>
     */
    private function claims(Request $request): array
    {
        $header = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : $header;
        if ($token === '' || $token === $header) {
            throw new InvalidCredentials('no session');
        }

        return (new JwtService)->verify($token);
    }

    // rails body 镜像：JSON.parse rescue {} —— parse 失败/空体/非对象体 → {}（不炸
    // 400——空账密分支自有校验）
    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $raw = $request->getContent();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        /** @var array<string, mixed> */
        return is_array($decoded) ? $decoded : [];
    }
}
