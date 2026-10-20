<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

// 业务域（contracts/samples/receipts/test-records）列表信封 + 404 语义 concern
// （rails app/controllers/concerns/business_envelope.rb 逐语义移植）。
// - 信封：page=1/pageSize=20 缺省（2026-09-16 T11 live 实证 1-based，lab-ct
//   contracts.test.ts "分页 defaults 全等" 锁定）；items 全量回（不切片，total =
//   items.size）——家族业务域信封口径，与码表域分页各照其 rails 源。
// - 404：业务域 id 带前缀（C-/R-/S-/TR-），基座 badUuidParam 的裸 UUID 启发式会把
//   前缀 id 折成 400——消费方 catch ModelNotFoundException 后自行
//   renderError(404, 'NOT_FOUND')（rails 覆写 rescue_from 同形），禁落基座 UUID 分叉。
//
// @phpstan-require-extends Controller
trait BusinessEnvelope
{
    // {items, page, pageSize, total} 信封；page/pageSize 仅 echo 不切片（参照实现实测）
    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function renderBusinessEnvelope(Request $request, array $items): JsonResponse
    {
        $page = $request->query('page');
        $pageSize = $request->query('pageSize');

        return $this->renderCamel([
            'items' => $items,
            'page' => $page === null ? 1 : (int) $page,
            'page_size' => $pageSize === null ? 20 : (int) $pageSize,
            'total' => count($items),
        ]);
    }

    // query param 归一：缺省/空串 → null（springboot null 语义）
    protected function queryParam(Request $request, string $name): ?string
    {
        $value = $request->query($name);
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    // JSON 请求体（缺 body → {}）
    /**
     * @return array<string, mixed>
     */
    protected function bodyParams(Request $request): array
    {
        /** @var array<string, mixed> */
        return $request->json()->all();
    }

    // springboot 枚举 query/body 参数反序列化镜像：未知值 → 400（IAE）
    /**
     * @param  list<string>  $allowed
     */
    protected function enumParam(?string $value, array $allowed): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("Unexpected value '{$value}'");
        }

        return $value;
    }
}
