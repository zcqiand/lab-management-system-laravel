<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\Samples\SampleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * samples 样品（B3，6 端点）：CRUD + ext 补录。tenant-scoped + receipt FK 校验
 * （lab-springboot SampleController 镜像 → rails SamplesController 同构移植；
 * 契约面 1:1 对 generated/api_manifest.json，tag samples，6 条）。
 *
 * 404 语义走 BusinessEnvelope（rails 覆写 rescue_from 同形）：查无一律 404 NOT_FOUND
 * 不落基座裸 UUID 启发式（id 形如 "S-<uuid>"）；create 的 receipt FK 显式查→404
 * 「Receipt not found: <id>」（Task 6 ContractService / Task 11 接样链先例）。
 */
final class SamplesController extends Controller
{
    use BusinessEnvelope;

    // @impl M03.F03.I01 (book anchor xr-know-012)
    public function listSamples(Request $request): JsonResponse
    {
        $items = $this->service($request)->list(
            $this->queryParam($request, 'receiptId'),
            $this->queryParam($request, 'keyword'),
        );

        return $this->renderBusinessEnvelope($request, $items);
    }

    // @impl M03.F03.I02 (book anchor xr-know-012)
    public function getSample(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->get($id));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Sample not found: {$id}");
        }
    }

    // @impl M03.F03.I03 (book anchor xr-know-012)
    public function createSample(Request $request): JsonResponse
    {
        $body = $this->bodyParams($request);
        $receiptId = (string) ($body['receiptId'] ?? '');

        try {
            return $this->renderCamel($this->service($request)->create($body));
        } catch (ModelNotFoundException) {
            // receipt FK 显式查→404（rails RecordNotFound "Receipt not found: <id>" 逐字）
            return $this->renderError(404, 'NOT_FOUND', "Receipt not found: {$receiptId}");
        }
    }

    // @impl M03.F03.I04 (book anchor xr-know-012)
    public function updateSample(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->update($id, $this->bodyParams($request)));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Sample not found: {$id}");
        }
    }

    // M03.F01.I07 ext 补录：body 必须含 ext 键（契约必填，5.89）
    // @impl M03.F01.I07 (book anchor xr-know-012)
    public function updateSampleExt(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel($this->service($request)->updateExt($id, $this->bodyParams($request)));
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Sample not found: {$id}");
        }
    }

    // @impl M03.F03.I05 (book anchor xr-know-012)
    public function deleteSample(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            $this->service($request)->delete($id);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Sample not found: {$id}");
        }

        return response()->json()->setStatusCode(204);
    }

    private function service(Request $request): SampleService
    {
        return new SampleService($this->currentTenantId($request));
    }
}
