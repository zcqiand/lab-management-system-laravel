<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * inspection-catalog 字典目录（brand/grade/model/spec，code 型 PK；契约面 1:1 对
 * generated/api_manifest.json，tag inspection-catalog，16 条）。
 *
 * M04.F06-F09 码表 controller（lab-springboot InspectionCatalogController 镜像 →
 * rails CatalogController 同构移植）。16 端点 4 字典同构；list 全量 List 不切页，
 * 包 Page 信封（page 缺省 1、pageSize 缺省 = items.size —— 2026-09-16 T11 live
 * 语义，照抄 controller 实测）。{code} 寻址 miss → 基座 404 NOT_FOUND（业务串码
 * 非 uuid 形参，不落 400 malformed 分叉）。
 */
final class InspectionCatalogController extends Controller
{
    // === Brand (M04.F09) ===
    public function listBrands(Request $request): JsonResponse
    {
        return $this->renderDictList($request, 'brand');
    }

    public function createBrand(Request $request): JsonResponse
    {
        return $this->renderCreate($request, 'brand');
    }

    public function updateBrand(Request $request): JsonResponse
    {
        return $this->renderUpdate($request, 'brand');
    }

    public function deleteBrand(Request $request): JsonResponse
    {
        return $this->renderDelete($request, 'brand');
    }

    // === Model (M04.F06) ===
    // @impl M04.F06.I01 (book anchor xr-know-012)
    public function listModels(Request $request): JsonResponse
    {
        return $this->renderDictList($request, 'model');
    }

    // @impl M04.F06.I02 (book anchor xr-know-012)
    public function createModel(Request $request): JsonResponse
    {
        return $this->renderCreate($request, 'model');
    }

    // @impl M04.F06.I03 (book anchor xr-know-012)
    public function updateModel(Request $request): JsonResponse
    {
        return $this->renderUpdate($request, 'model');
    }

    // @impl M04.F06.I04 (book anchor xr-know-012)
    public function deleteModel(Request $request): JsonResponse
    {
        return $this->renderDelete($request, 'model');
    }

    // === Spec (M04.F07) ===
    // @impl M04.F07.I01 (book anchor xr-know-012)
    public function listSpecs(Request $request): JsonResponse
    {
        return $this->renderDictList($request, 'spec');
    }

    // @impl M04.F07.I02 (book anchor xr-know-012)
    public function createSpec(Request $request): JsonResponse
    {
        return $this->renderCreate($request, 'spec');
    }

    // @impl M04.F07.I03 (book anchor xr-know-012)
    public function updateSpec(Request $request): JsonResponse
    {
        return $this->renderUpdate($request, 'spec');
    }

    // @impl M04.F07.I04 (book anchor xr-know-012)
    public function deleteSpec(Request $request): JsonResponse
    {
        return $this->renderDelete($request, 'spec');
    }

    // === Grade (M04.F08) ===
    public function listGrades(Request $request): JsonResponse
    {
        return $this->renderDictList($request, 'grade');
    }

    public function createGrade(Request $request): JsonResponse
    {
        return $this->renderCreate($request, 'grade');
    }

    public function updateGrade(Request $request): JsonResponse
    {
        return $this->renderUpdate($request, 'grade');
    }

    public function deleteGrade(Request $request): JsonResponse
    {
        return $this->renderDelete($request, 'grade');
    }

    // ==== rails private 段同位 ====

    // 全量 items + 信封（page/pageSize 只回显不切页，镜像 springboot effectivePage/effectivePageSize）
    private function renderDictList(Request $request, string $kind): JsonResponse
    {
        $items = (new CatalogService)->list(
            $kind,
            $this->currentTenantId($request),
            $this->optionalQuery($request, 'inspectionObjectCode'),
            $this->optionalQuery($request, 'keyword')
        );
        $page = $request->query('page');
        $pageSize = $request->query('pageSize');

        return $this->renderCamel([
            'items' => $items,
            'page' => $page === null ? 1 : (int) $page,
            'page_size' => $pageSize === null ? count($items) : (int) $pageSize,
            'total' => count($items),
        ]);
    }

    private function renderCreate(Request $request, string $kind): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->json()->all();

        return $this->renderCamel(
            (new CatalogService)->create($kind, $this->currentTenantId($request), $body)
        );
    }

    private function renderUpdate(Request $request, string $kind): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = $request->json()->all();

        return $this->renderCamel(
            (new CatalogService)->update(
                $kind,
                $this->currentTenantId($request),
                (string) $request->route('code'),
                $body
            )
        );
    }

    private function renderDelete(Request $request, string $kind): JsonResponse
    {
        (new CatalogService)->delete(
            $kind,
            $this->currentTenantId($request),
            (string) $request->route('code')
        );

        return response()->json()->setStatusCode(204);
    }

    // query param 直通（present? 归一在 service 侧，rails params[:x] → service.present? 同形）
    private function optionalQuery(Request $request, string $name): ?string
    {
        $value = $request->query($name);

        return is_string($value) ? $value : null;
    }
}
