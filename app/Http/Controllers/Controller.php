<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\InvalidCredentials;
use App\Services\Auth\JwtService;
use App\Support\Camel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

// 家族契约底座（rails app/controllers/application_controller.rb 逐语义移植；saas
// laravel 同名件同构）：camelCase JSON 渲染 + 错误体 {code,message} 助手 + 分页信封 +
// UUID 参数甄别。错误映射表在 bootstrap/app.php withExceptions（rails rescue_from
// 表逐行镜像，ADR-0020 错误契约）；本基座供 Task 5-13 控制器消费渲染/分页/甄别面。
abstract class Controller
{
    // rails \z 严格语义镜像（D 修饰符禁 $ 匹配尾随换行——否则 "uuid\n" 被当合法
    // 格式走 404「查无」而非 400「malformed uuid」）。
    private const UUID_RE = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D';

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

    /**
     * snake_case → lowerCamel 深变换渲染（家族 DTO 形状是 camelCase，DB 列是 snake_case）。
     *
     * @param  array<array-key, mixed>  $data
     */
    protected function renderCamel(array $data, int $status = 200): JsonResponse
    {
        return response()->json(Camel::deep($data), $status);
    }

    protected function renderError(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message], $status);
    }

    /**
     * 分页参数（rails page_params 镜像）：page=0 / pageSize=20 缺省（query key 是
     * camelCase 的 pageSize，契约口径）；page<0 或 pageSize<1 → IAE → 基座 400
     * BAD_REQUEST。
     *
     * @return array{0: int, 1: int} [page, pageSize]
     */
    protected function pageParams(Request $request): array
    {
        $page = (int) $request->query('page', '0');
        $pageSize = (int) $request->query('pageSize', '20');
        if ($page < 0) {
            throw new InvalidArgumentException('page must be >= 0');
        }
        if ($pageSize < 1) {
            throw new InvalidArgumentException('pageSize must be >= 1');
        }

        return [$page, $pageSize];
    }

    /**
     * 家族分页信封 { items, page, pageSize, total }（contract-test 约定）：count 全量、
     * limit/offset 切页、mapper 逐行装配后统一 renderCamel（page_size 经 Camel 转
     * pageSize 出线）。
     *
     * @param  Builder<Model>  $query
     * @param  callable(mixed): array<array-key, mixed>  $mapper
     */
    protected function renderPaginated(Builder $query, callable $mapper): JsonResponse
    {
        [$page, $pageSize] = $this->pageParams(request());
        $total = (clone $query)->count();
        $items = $query->offset($page * $pageSize)->limit($pageSize)->get()
            ->map(fn ($row): array => $mapper($row))
            ->all();

        return $this->renderCamel([
            'items' => $items,
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
        ]);
    }

    /**
     * oauth 链手动 decode（rails ApplicationController#decode_bearer! 镜像：springboot
     * SecurityConfig 独立 permitAll 链——oauth 控制器不挂 JwtGuard（匿名五路由），
     * decode 语义放基座与 rails 同位）：无/坏 Bearer → 401（INVALID_CREDENTIALS）；
     * 验签失败/过期走 TokenExpired/TokenInvalid 由映射表统一 401；无 tenant_id claim
     * → InvalidCredentials（rails missing tenant_id claim 镜像）。
     *
     * @return array<string, mixed> JWT payload（sub / tenant_id / ...）
     */
    protected function decodeBearer(Request $request): array
    {
        $header = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : $header;
        if ($token === '' || $token === $header) {
            throw new InvalidCredentials('missing bearer token');
        }

        // TokenExpired/TokenInvalid 不在此吞——基座映射表直接转 401（rails rescue JWT::* 同形）
        $payload = (new JwtService)->verify($token);

        $tenantId = $payload['tenant_id'] ?? null;
        if (! is_string($tenantId) || $tenantId === '') {
            throw new InvalidCredentials('missing tenant_id claim');
        }

        return $payload;
    }

    /**
     * rails bad_uuid_param 镜像：ModelNotFound 渲染前的 UUID 甄别——路径段声称 uuid
     * 但格式非法 → 返回首个坏值（基座映射转 400 malformed uuid）；合法格式查无 →
     * null（404）。
     *
     * 扫描键与 lab 路由 uuid 形参实态对齐：routes/api.php 形参全集 =
     * {id}/{code}/{inspectionObjectCode}/{inspectionParameterCode}/{judgmentStandardCode}，
     * 其中 uuid 形参只有 {id}——{code} 系列是业务串码，rails bad_uuid_param 扫描表
     * （id/tenant_id/user_id/role_id/menu_id）不含 :code，码 miss 走 404（inspection
     * dictionary services 对码 miss raise RecordNotFound → 404 NOT_FOUND 镜像）。
     * saas 集合（tenantId/userId/roleId/menuId）lab 路由不存在，勿照抄。
     */
    public static function badUuidParam(Request $request): ?string
    {
        foreach (['id'] as $key) {
            $value = $request->route($key);
            if (is_string($value) && $value !== '' && preg_match(self::UUID_RE, $value) !== 1) {
                return $value;
            }
        }

        return null;
    }
}
