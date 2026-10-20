<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\CalculationMethod\CalculationMethodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M06.F05 计算方法 controller（lab-springboot CalculationMethodController 镜像 →
 * rails CalculationMethodsController 同构移植）。5 端点；平台级字典不读 JWT
 * tenant claim（仍挂 JwtGuard）；list 返回裸数组（无分页信封）。
 * 404 消息逐字照 rails found!：「CalculationMethod not found: <object>/<parameter>」。
 */
final class CalculationMethodsController extends Controller
{
    use BusinessEnvelope;

    // @impl M06.F05.I01 (book anchor xr-know-012)
    public function listCalculationMethods(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new CalculationMethodService)->list(
                $this->queryParam($request, 'inspectionObjectCode'),
                $this->queryParam($request, 'inspectionParameterCode')
            )
        );
    }

    // @impl M06.F05.I02 (book anchor xr-know-012)
    public function getCalculationMethod(Request $request): JsonResponse
    {
        [$objectCode, $parameterCode] = $this->pathKeys($request);
        try {
            return $this->renderCamel(
                (new CalculationMethodService)->get($objectCode, $parameterCode)
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(
                404,
                'NOT_FOUND',
                "CalculationMethod not found: {$objectCode}/{$parameterCode}"
            );
        }
    }

    // @impl M06.F05.I03 (book anchor xr-know-012)
    public function createCalculationMethod(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new CalculationMethodService)->create($this->bodyParams($request))
        );
    }

    // @impl M06.F05.I04 (book anchor xr-know-012)
    public function updateCalculationMethod(Request $request): JsonResponse
    {
        [$objectCode, $parameterCode] = $this->pathKeys($request);
        try {
            return $this->renderCamel(
                (new CalculationMethodService)->update($objectCode, $parameterCode, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(
                404,
                'NOT_FOUND',
                "CalculationMethod not found: {$objectCode}/{$parameterCode}"
            );
        }
    }

    // @impl M06.F05.I05 (book anchor xr-know-012)
    public function deleteCalculationMethod(Request $request): JsonResponse
    {
        [$objectCode, $parameterCode] = $this->pathKeys($request);
        try {
            (new CalculationMethodService)->delete($objectCode, $parameterCode);
        } catch (ModelNotFoundException) {
            return $this->renderError(
                404,
                'NOT_FOUND',
                "CalculationMethod not found: {$objectCode}/{$parameterCode}"
            );
        }

        return response()->json()->setStatusCode(204);
    }

    // ==== private 段 ====

    /**
     * @return array{0: string, 1: string}
     */
    private function pathKeys(Request $request): array
    {
        return [
            (string) $request->route('inspectionObjectCode'),
            (string) $request->route('inspectionParameterCode'),
        ];
    }
}
