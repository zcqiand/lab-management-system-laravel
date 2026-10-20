<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\TechnicalRequirement\TechnicalRequirementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M06.F06 技术要求 controller（lab-springboot TechnicalRequirementController 镜像 →
 * rails TechnicalRequirementsController 同构移植）。5 端点；tenant 从 JWT claim 取
 * （基座 currentTenantId，rails JwtGuard#current_tenant_id 同位）；list 返回裸数组。
 * 404 消息逐字照 rails found!：
 * 「TechnicalRequirement not found: <object>/<parameter>/<standard>」。
 */
final class TechnicalRequirementsController extends Controller
{
    use BusinessEnvelope;

    // @impl M06.F06.I01 (book anchor xr-know-012)
    public function listTechnicalRequirements(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new TechnicalRequirementService($this->currentTenantId($request)))->list(
                $this->queryParam($request, 'inspectionObjectCode'),
                $this->queryParam($request, 'inspectionParameterCode'),
                $this->queryParam($request, 'judgmentStandardCode'),
                $this->queryParam($request, 'verificationStatus')
            )
        );
    }

    // @impl M06.F06.I02 (book anchor xr-know-012)
    public function getTechnicalRequirement(Request $request): JsonResponse
    {
        $keys = $this->pathKeys($request);
        try {
            return $this->renderCamel(
                $this->service($request)->get($keys[0], $keys[1], $keys[2])
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', $this->notFoundMessage($keys));
        }
    }

    // @impl M06.F06.I03 (book anchor xr-know-012)
    public function createTechnicalRequirement(Request $request): JsonResponse
    {
        return $this->renderCamel(
            $this->service($request)->create($this->bodyParams($request))
        );
    }

    // @impl M06.F06.I04 (book anchor xr-know-012)
    public function updateTechnicalRequirement(Request $request): JsonResponse
    {
        $keys = $this->pathKeys($request);
        try {
            return $this->renderCamel(
                $this->service($request)->update($keys[0], $keys[1], $keys[2], $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', $this->notFoundMessage($keys));
        }
    }

    // @impl M06.F06.I05 (book anchor xr-know-012)
    public function deleteTechnicalRequirement(Request $request): JsonResponse
    {
        $keys = $this->pathKeys($request);
        try {
            $this->service($request)->delete($keys[0], $keys[1], $keys[2]);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', $this->notFoundMessage($keys));
        }

        return response()->json()->setStatusCode(204);
    }

    // ==== private 段（rails 同位：tenant / object_code / parameter_code / standard_code / body） ====

    private function service(Request $request): TechnicalRequirementService
    {
        return new TechnicalRequirementService($this->currentTenantId($request));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function pathKeys(Request $request): array
    {
        return [
            (string) $request->route('inspectionObjectCode'),
            (string) $request->route('inspectionParameterCode'),
            (string) $request->route('judgmentStandardCode'),
        ];
    }

    /**
     * @param  array{0: string, 1: string, 2: string}  $keys
     */
    private function notFoundMessage(array $keys): string
    {
        return "TechnicalRequirement not found: {$keys[0]}/{$keys[1]}/{$keys[2]}";
    }
}
