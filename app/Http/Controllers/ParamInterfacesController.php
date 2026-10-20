<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\InspectionDictionary\InspectionJunctionService;
use App\Services\ParamInterface\ParamInterfaceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M10 参数接口 controller（lab-springboot ParamInterfaceController 镜像 →
 * rails ParamInterfacesController 同构移植；契约面 1:1 对 generated/api_manifest.json，
 * tag param-interfaces，8 条）。5 CRUD 端点 + junction link/unlink（恒 204，
 * unlink 幂等不校验键）+ links list（短信封 page=1）。
 *
 * 路由侧 /links 静态段先于 /{code} 注册（routes/api.php 1:1 manifest 序），rails
 * 的 get_param_interface 'links' 转发分支在本仓无需——静态路由天然优先。
 */
final class ParamInterfacesController extends Controller
{
    use BusinessEnvelope;

    // @impl M06.F08.I01 (book anchor xr-know-012)
    public function listParamInterfaces(Request $request): JsonResponse
    {
        $items = (new ParamInterfaceService)->list($this->queryParam($request, 'keyword'));
        $page = $request->query('page');
        $pageSize = $request->query('pageSize');

        return $this->renderCamel([
            'items' => $items,
            'page' => $page === null ? 1 : (int) $page,
            'page_size' => $pageSize === null ? count($items) : (int) $pageSize,
            'total' => count($items),
        ]);
    }

    // @impl M06.F08.I02 (book anchor xr-know-012)
    public function getParamInterface(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel((new ParamInterfaceService)->get($code));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ParamInterface not found: {$code}");
        }
    }

    // @impl M06.F08.I03 (book anchor xr-know-012)
    public function createParamInterface(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new ParamInterfaceService)->create($this->bodyParams($request))
        );
    }

    // @impl M06.F08.I04 (book anchor xr-know-012)
    public function updateParamInterface(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new ParamInterfaceService)->update($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ParamInterface not found: {$code}");
        }
    }

    // @impl M06.F08.I05 (book anchor xr-know-012)
    public function deleteParamInterface(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new ParamInterfaceService)->delete($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ParamInterface not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    public function linkParamInterface(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkParamInterface($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkParamInterface(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkParamInterface(
            $this->bodyString($body, 'inspectionParameterCode'),
            $this->bodyString($body, 'paramInterfaceCode')
        );

        return response()->json()->setStatusCode(204);
    }

    public function listParamInterfaceLinks(Request $request): JsonResponse
    {
        $items = (new InspectionJunctionService)->listParamInterfaceLinks(
            $this->queryParam($request, 'inspectionParameterCode'),
            $this->queryParam($request, 'paramInterfaceCode')
        );

        return $this->renderCamel([
            'items' => $items,
            'page' => 1,
            'page_size' => count($items),
            'total' => count($items),
        ]);
    }

    // ==== rails private 段同位 ====

    // body 取串（rails body['x'] 镜像：缺键 → null，service 侧定位无 match 即 no-op）
    /**
     * @param  array<string, mixed>  $body
     */
    private function bodyString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
