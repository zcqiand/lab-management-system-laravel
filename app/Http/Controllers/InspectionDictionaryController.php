<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\InspectionDictionary\InspectionDictionaryService;
use App\Services\InspectionDictionary\InspectionJunctionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * inspection-dictionary 检测字典（object/parameter/specialty/standard + links；契约面
 * 1:1 对 generated/api_manifest.json，tag inspection-dictionary，28 条）。
 *
 * M06 检测能力字典 controller（lab-springboot InspectionDictionaryController 镜像 →
 * rails InspectionDictionaryController + InspectionDictionaryLinks concern 同构移植）。
 * 四实体 CRUD：list 用 wrapDict 短信封（page 缺省 1、pageSize 缺省 = items.size）；
 * {code} 寻址 miss → 404 NOT_FOUND '<Model> not found: <code>'（rails 逐字；业务串码
 * 非路由 uuid 形参，不落 400 malformed 分叉）。junction link/unlink 恒 204，
 * junction list 用短信封 page 恒 1（镜像 springboot 200Response 硬编码）。
 */
final class InspectionDictionaryController extends Controller
{
    use BusinessEnvelope;

    // === specialties (M06.F01) ===

    // @impl M06.F01.I01 (book anchor xr-know-012)
    public function listSpecialties(Request $request): JsonResponse
    {
        return $this->renderDictList(
            $request,
            (new InspectionDictionaryService)->listSpecialties($this->queryParam($request, 'keyword'))
        );
    }

    // @impl M06.F01.I02 (book anchor xr-know-012)
    public function createSpecialty(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new InspectionDictionaryService)->createSpecialty($this->bodyParams($request))
        );
    }

    // @impl M06.F01.I03 (book anchor xr-know-012)
    public function updateSpecialty(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new InspectionDictionaryService)->updateSpecialty($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionSpecialty not found: {$code}");
        }
    }

    // @impl M06.F01.I04 (book anchor xr-know-012)
    public function deleteSpecialty(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new InspectionDictionaryService)->deleteSpecialty($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionSpecialty not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    // === objects (M06.F02) ===

    // @impl M06.F02.I01 (book anchor xr-know-012)
    public function listObjects(Request $request): JsonResponse
    {
        return $this->renderDictList(
            $request,
            (new InspectionDictionaryService)->listObjects(
                $this->queryParam($request, 'keyword'),
                $this->queryParam($request, 'inspectionSpecialtyCode')
            )
        );
    }

    // @impl M06.F02.I02 (book anchor xr-know-012)
    public function createObject(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new InspectionDictionaryService)->createObject($this->bodyParams($request))
        );
    }

    // @impl M06.F02.I03 (book anchor xr-know-012)
    public function updateObject(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new InspectionDictionaryService)->updateObject($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionObject not found: {$code}");
        }
    }

    // @impl M06.F02.I04 (book anchor xr-know-012)
    public function deleteObject(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new InspectionDictionaryService)->deleteObject($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionObject not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    // === parameters (M06.F03) ===

    // @impl M06.F03.I01 (book anchor xr-know-012)
    public function listParameters(Request $request): JsonResponse
    {
        return $this->renderDictList(
            $request,
            (new InspectionDictionaryService)->listParameters(
                $this->queryParam($request, 'keyword'),
                $this->queryParam($request, 'sourceType')
            )
        );
    }

    // @impl M06.F03.I02 (book anchor xr-know-012)
    public function createParameter(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new InspectionDictionaryService)->createParameter($this->bodyParams($request))
        );
    }

    // @impl M06.F03.I03 (book anchor xr-know-012)
    public function updateParameter(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new InspectionDictionaryService)->updateParameter($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionParameter not found: {$code}");
        }
    }

    // @impl M06.F03.I04 (book anchor xr-know-012)
    public function deleteParameter(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new InspectionDictionaryService)->deleteParameter($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionParameter not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    // === standards (M06.F04) ===

    public function listStandards(Request $request): JsonResponse
    {
        return $this->renderDictList(
            $request,
            (new InspectionDictionaryService)->listStandards(
                $this->queryParam($request, 'keyword'),
                $this->queryParam($request, 'status')
            )
        );
    }

    public function createStandard(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new InspectionDictionaryService)->createStandard($this->bodyParams($request))
        );
    }

    public function updateStandard(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new InspectionDictionaryService)->updateStandard($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionStandard not found: {$code}");
        }
    }

    public function deleteStandard(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new InspectionDictionaryService)->deleteStandard($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "InspectionStandard not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    // === junctions（rails InspectionDictionaryLinks concern 同位） ===

    // link/unlink 恒 204（unlink 幂等，不校验键）

    public function linkSpecialtyObject(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkSpecialtyObject($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkSpecialtyObject(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->unlinkSpecialtyObject($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function listSpecialtyObjectLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listSpecialtyObjectLinks(
                $this->queryParam($request, 'inspectionSpecialtyCode')
            )
        );
    }

    public function linkObjectParameter(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkObjectParameter($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkObjectParameter(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkObjectParameter(
            $this->bodyString($body, 'inspectionObjectCode'),
            $this->bodyString($body, 'inspectionParameterCode')
        );

        return response()->json()->setStatusCode(204);
    }

    public function listObjectParameterLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listObjectParameterLinks(
                $this->queryParam($request, 'inspectionObjectCode'),
                $this->queryParam($request, 'inspectionParameterCode')
            )
        );
    }

    // @impl M06.F01.I05 (book anchor xr-know-012)（rails link_object_standard 同锚）
    public function linkObjectStandard(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkObjectStandard($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    // @impl M06.F01.I06 (book anchor xr-know-012)
    public function unlinkObjectStandard(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkObjectStandard(
            $this->bodyString($body, 'inspectionObjectCode'),
            $this->bodyString($body, 'inspectionStandardCode'),
            $this->bodyString($body, 'role')
        );

        return response()->json()->setStatusCode(204);
    }

    // @impl M06.F01.I07 (book anchor xr-know-012)
    public function listObjectStandardLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listObjectStandardLinks(
                $this->queryParam($request, 'inspectionObjectCode'),
                $this->queryParam($request, 'role')
            )
        );
    }

    // @impl M06.F03.I05 (book anchor xr-know-012)（rails link_standard_parameter 同锚）
    public function linkStandardParameter(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkStandardParameter($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    // @impl M06.F03.I06 (book anchor xr-know-012)
    public function unlinkStandardParameter(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->unlinkStandardParameter($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    // @impl M06.F03.I08 (book anchor xr-know-012)
    public function listStandardParameterLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listStandardParameterLinks(
                $this->queryParam($request, 'inspectionStandardCode'),
                $this->queryParam($request, 'inspectionParameterCode')
            )
        );
    }

    // ==== rails private 段同位 ====

    // 字典 list 信封：page 缺省 1、pageSize 缺省 = items.size（wrapDict 语义）
    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function renderDictList(Request $request, array $items): JsonResponse
    {
        $page = $request->query('page');
        $pageSize = $request->query('pageSize');

        return $this->renderCamel([
            'items' => $items,
            'page' => $page === null ? 1 : (int) $page,
            'page_size' => $pageSize === null ? count($items) : (int) $pageSize,
            'total' => count($items),
        ]);
    }

    // junction list 信封：page 恒 1（镜像 springboot 200Response 硬编码），DTO 不含时间戳
    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function renderJunctionList(array $items): JsonResponse
    {
        return $this->renderCamel([
            'items' => $items,
            'page' => 1,
            'page_size' => count($items),
            'total' => count($items),
        ]);
    }

    // body 取串（rails body['x'] 镜像：缺键 → null，service 侧 find_by 无 match 即 no-op）
    /**
     * @param  array<string, mixed>  $body
     */
    private function bodyString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
