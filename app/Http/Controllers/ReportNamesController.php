<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\InspectionDictionary\InspectionJunctionService;
use App\Services\ReportNames\InspectionReportNameService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M06.F07 报告名称 controller（lab-springboot InspectionReportNameController 镜像 →
 * rails ReportNamesController 同构移植；契约面 1:1 对 generated/api_manifest.json，
 * tag report-names，14 条）。5 CRUD 端点 + 3 类 junction link/unlink/list：
 * {code} 寻址 miss → 404 NOT_FOUND 'ReportName not found: <code>'（rails 逐字）；
 * junction link/unlink 恒 204（unlink 幂等不校验键），junction list 用短信封
 * page 恒 1（镜像 springboot 200Response 硬编码）。
 */
final class ReportNamesController extends Controller
{
    use BusinessEnvelope;

    public function listReportNames(Request $request): JsonResponse
    {
        return $this->renderDictList(
            $request,
            (new InspectionReportNameService)->list($this->queryParam($request, 'keyword'))
        );
    }

    public function getReportName(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel((new InspectionReportNameService)->get($code));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ReportName not found: {$code}");
        }
    }

    public function createReportName(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new InspectionReportNameService)->create($this->bodyParams($request))
        );
    }

    public function updateReportName(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            return $this->renderCamel(
                (new InspectionReportNameService)->update($code, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ReportName not found: {$code}");
        }
    }

    public function deleteReportName(Request $request): JsonResponse
    {
        $code = (string) $request->route('code');
        try {
            (new InspectionReportNameService)->delete($code);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "ReportName not found: {$code}");
        }

        return response()->json()->setStatusCode(204);
    }

    // === junction link/unlink（B6；link/unlink 恒 204，unlink 幂等不校验键）===

    public function linkObjectReportName(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkObjectReportName($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkObjectReportName(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkObjectReportName(
            $this->bodyString($body, 'inspectionObjectCode'),
            $this->bodyString($body, 'reportNameCode')
        );

        return response()->json()->setStatusCode(204);
    }

    public function listObjectReportNameLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listObjectReportNameLinks(
                $this->queryParam($request, 'inspectionObjectCode'),
                $this->queryParam($request, 'reportNameCode')
            )
        );
    }

    public function linkReportNameParameter(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkReportNameParameter($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkReportNameParameter(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkReportNameParameter(
            $this->bodyString($body, 'reportNameCode'),
            $this->bodyString($body, 'inspectionParameterCode')
        );

        return response()->json()->setStatusCode(204);
    }

    public function listReportNameParameterLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listReportNameParameterLinks(
                $this->queryParam($request, 'reportNameCode'),
                $this->queryParam($request, 'inspectionParameterCode')
            )
        );
    }

    public function linkReportNameStandard(Request $request): JsonResponse
    {
        (new InspectionJunctionService)->linkReportNameStandard($this->bodyParams($request));

        return response()->json()->setStatusCode(204);
    }

    public function unlinkReportNameStandard(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        (new InspectionJunctionService)->unlinkReportNameStandard(
            $this->bodyString($body, 'reportNameCode'),
            $this->bodyString($body, 'inspectionStandardCode'),
            $this->bodyString($body, 'role')
        );

        return response()->json()->setStatusCode(204);
    }

    public function listReportNameStandardLinks(Request $request): JsonResponse
    {
        return $this->renderJunctionList(
            (new InspectionJunctionService)->listReportNameStandardLinks(
                $this->queryParam($request, 'reportNameCode'),
                $this->queryParam($request, 'role')
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
