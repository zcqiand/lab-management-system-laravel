<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Concerns\BusinessEnvelope;
use App\Services\Contracts\ContractService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * contracts 委托合同（M02，tenant 隔离；lab-springboot ContractController 镜像 →
 * rails ContractsController 同构移植，B3，5 端点）。契约面 1:1 对
 * generated/api_manifest.json（tag contracts，5 条）。
 *
 * 404 语义走 BusinessEnvelope（rails 覆写 rescue_from 同形）：查无一律 404
 * NOT_FOUND，不落基座裸 UUID 启发式（id 形如 "C-<uuid>"）。
 */
final class ContractsController extends Controller
{
    use BusinessEnvelope;

    // @impl M02.F01.I01 (book anchor xr-know-012)
    public function listContracts(Request $request): JsonResponse
    {
        $service = new ContractService($this->currentTenantId($request));
        $status = $this->enumParam($this->queryParam($request, 'status'), ContractService::STATUSES);
        $items = $service->list($this->queryParam($request, 'keyword'), $status);

        return $this->renderBusinessEnvelope($request, $items);
    }

    // @impl M02.F01.I02 (book anchor xr-know-012)
    public function getContract(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel(
                (new ContractService($this->currentTenantId($request)))->get($id)
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Contract not found: {$id}");
        }
    }

    // @impl M02.F01.I03 (book anchor xr-know-012)
    public function createContract(Request $request): JsonResponse
    {
        return $this->renderCamel(
            (new ContractService($this->currentTenantId($request)))->create($this->bodyParams($request))
        );
    }

    // @impl M02.F01.I04 (book anchor xr-know-012)
    public function updateContract(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            return $this->renderCamel(
                (new ContractService($this->currentTenantId($request)))->update($id, $this->bodyParams($request))
            );
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Contract not found: {$id}");
        }
    }

    // @impl M02.F01.I05 (book anchor xr-know-012)
    public function deleteContract(Request $request): JsonResponse
    {
        $id = (string) $request->route('id');

        try {
            (new ContractService($this->currentTenantId($request)))->delete($id);
        } catch (ModelNotFoundException) {
            return $this->renderError(404, 'NOT_FOUND', "Contract not found: {$id}");
        }

        return response()->json()->setStatusCode(204);
    }
}
