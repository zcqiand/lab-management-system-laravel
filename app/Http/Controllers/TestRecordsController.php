<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\TestRecords\TestRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * test-records 检测记录（B9.3，6 端点）：CRUD + verdict 人工改判。tenant 收口走
 * JWT claim（lab-springboot TestRecordController 镜像 → rails TestRecordsController
 * 同构移植；契约面 1:1 对 generated/api_manifest.json，tag test-records，6 条）。
 *
 * 404 语义走 BusinessEnvelope（rails 覆写 rescue_from 同形）：查无一律 404 NOT_FOUND
 * 不落基座裸 UUID 启发式（id 形如 "TR-<uuid>"）；create 的 sample/parameter FK 走
 * DB 约束→400 "constraint violation"（与 receipts/samples 显式查 404 有意分叉）。
 */
final class TestRecordsController extends Controller
{
    use BusinessEnvelope;

    // @impl M03.F03.I06 (book anchor xr-know-012)
    public function listTestRecords(Request $request): JsonResponse
    {
        $items = $this->service($request)->list($this->queryParam($request, 'sampleId'));

        return $this->renderBusinessEnvelope($request, $items);
    }

    // @impl M03.F03.I07 (book anchor xr-know-012)
    public function getTestRecord(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->get($id));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "TestRecord not found: {$id}");
        }
    }

    // @impl M03.F03.I08 (book anchor xr-know-012)
    public function createTestRecord(Request $request): JsonResponse
    {
        return $this->renderCamel($this->service($request)->create($this->bodyParams($request)));
    }

    // @impl M03.F03.I09 (book anchor xr-know-012)
    public function updateTestRecord(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->update($id, $this->bodyParams($request)));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "TestRecord not found: {$id}");
        }
    }

    // M03.F03 人工改判 verdict（verdict 直写，可置 null——strict 口径）
    // @impl M03.F03.I11 (book anchor xr-know-012)
    public function setVerdict(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            $verdict = $this->bodyParams($request)['verdict'] ?? null;
            $verdict = is_string($verdict) ? $verdict : null;

            return $this->renderCamel($this->service($request)->setVerdict($id, $verdict));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "TestRecord not found: {$id}");
        }
    }

    // @impl M03.F03.I10 (book anchor xr-know-012)
    public function deleteTestRecord(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            $this->service($request)->delete($id);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "TestRecord not found: {$id}");
        }

        return response()->json()->setStatusCode(204);
    }

    private function service(Request $request): TestRecordService
    {
        return new TestRecordService($this->currentTenantId($request));
    }
}
