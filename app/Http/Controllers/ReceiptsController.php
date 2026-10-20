<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\Receipts\ReportFlowService;
use App\Services\Receipts\SampleReceiptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M03.F01/F02/F05-F09 接样单（B3）：CRUD + history + 任务分配 + 7 阶段全 act 模式
 * （lab-springboot SampleReceiptController 镜像 → rails ReceiptsController 同构移植，
 * 2026-09-17 收敛版 7 act op；契约面 1:1 对 generated/api_manifest.json，tag receipts，
 * 14 条）。
 *
 * 404 语义走 BusinessEnvelope（rails 覆写 rescue_from 同形）：查无一律 404 NOT_FOUND
 * 不落基座裸 UUID 启发式（id 形如 "R-<uuid>"）；create 的 contract FK 显式查→404
 * 「Contract not found: <id>」（Task 6 ContractService 先例）。
 */
final class ReceiptsController extends Controller
{
    use BusinessEnvelope;

    // @impl M03.F01.I01 (book anchor xr-know-012)
    public function listReceipts(Request $request): JsonResponse
    {
        $items = $this->service($request)->list(
            $this->queryParam($request, 'contractId'),
            $this->enumParam($this->queryParam($request, 'flowStatus'), SampleReceiptService::FLOW_STATUSES),
            $this->queryParam($request, 'keyword'),
            $this->queryParam($request, 'filter'),
        );

        return $this->renderBusinessEnvelope($request, $items);
    }

    // @impl M03.F01.I02 (book anchor xr-know-012)
    // @impl M03.F05.I02 (book anchor xr-know-012)
    // @impl M03.F06.I02 (book anchor xr-know-012)
    // @impl M03.F07.I02 (book anchor xr-know-012)
    // @impl M03.F08.I02 (book anchor xr-know-012)
    // @impl M03.F09.I01 (book anchor xr-know-012)
    public function getReceipt(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->get($id));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$id}");
        }
    }

    // @impl M03.F01.I03 (book anchor xr-know-012)
    public function createReceipt(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        $contractId = (string) ($body['contractId'] ?? '');

        try {
            return $this->renderCamel($this->service($request)->create($body));
        } catch (ModelNotFoundException) {
            // contract FK 显式查→404（rails RecordNotFound "Contract not found: <id>" 逐字）
            return $this->renderError(404, 'NOT_FOUND', "Contract not found: {$contractId}");
        }
    }

    // @impl M03.F01.I04 (book anchor xr-know-012)
    public function updateReceipt(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->update($id, $this->bodyParams($request)));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$id}");
        }
    }

    // @impl M03.F01.I05 (book anchor xr-know-012)
    public function deleteReceipt(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            $this->service($request)->delete($id);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$id}");
        }

        return response()->json()->setStatusCode(204);
    }

    // M03.F01.I06 流程历史（jsonb → List<FlowHistoryEntry>）
    // @impl M03.F01.I06 (book anchor xr-know-012)
    public function getReceiptHistory(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->history($id));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$id}");
        }
    }

    // M03.F02 任务分配
    // @impl M03.F02.I01 (book anchor xr-know-012)
    public function assignTask(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel(
                $this->service($request)->assignTask($id, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$id}");
        }
    }

    // ── 7 个 act 端点：共享 body {ids[], action, operator, reason?}，stage 由路径定 ──
    // @impl M03.F01.I08 (book anchor xr-know-012)
    public function actFlowReceiving(Request $request): JsonResponse
    {
        return $this->actFlow('receiving', $request);
    }

    // @impl M03.F02.I05 (book anchor xr-know-012)
    public function actFlowAssigning(Request $request): JsonResponse
    {
        return $this->actFlow('task_assignment', $request);
    }

    // @impl M03.F03.I12 (book anchor xr-know-012)
    public function actFlowDataEntry(Request $request): JsonResponse
    {
        return $this->actFlow('data_entry', $request);
    }

    // @impl M03.F05.I07 (book anchor xr-know-012)
    public function actFlowReview(Request $request): JsonResponse
    {
        return $this->actFlow('review', $request);
    }

    // @impl M03.F06.I05 (book anchor xr-know-012)
    public function actFlowApprove(Request $request): JsonResponse
    {
        return $this->actFlow('approval', $request);
    }

    // @impl M03.F07.I05 (book anchor xr-know-012)
    public function actFlowIssuance(Request $request): JsonResponse
    {
        return $this->actFlow('issuance', $request);
    }

    // @impl M03.F08.I05 (book anchor xr-know-012)
    public function actFlowArchived(Request $request): JsonResponse
    {
        return $this->actFlow('archived', $request);
    }

    // ==== private 段（rails 同位） ====

    private function service(Request $request): SampleReceiptService
    {
        return new SampleReceiptService($this->currentTenantId($request));
    }

    private function actFlow(string $stage, Request $request): JsonResponse
    {
        $flowService = new ReportFlowService($this->service($request));

        return $this->renderCamel($flowService->act($stage, $this->bodyParams($request)));
    }
}
