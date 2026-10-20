<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Models\SampleReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// 接样单列表查询谓词（lab-springboot SampleReceiptMapper 的 WHERE 镜像 →
// rails Receipts::ReceiptQueries 同构移植）：keyword LIKE 与 5.57 三态 filter
// （not_yet/submitted，jsonb 谓词 native SQL）。
trait ReceiptQueries
{
    /**
     * @param  Builder<SampleReceipt>  $query
     * @return Builder<SampleReceipt>
     */
    private function filterKeyword(Builder $query, ?string $keyword): Builder
    {
        $needle = $keyword === null ? '' : mb_strtolower($keyword);
        if ($needle === '') {
            return $query;
        }

        $like = '%'.$needle.'%';

        // rails LOWER(commission_code) LIKE :k OR LOWER(project_name) LIKE :k 镜像
        return $query->where(static function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(commission_code) LIKE ?', [$like])
                ->orWhereRaw('LOWER(project_name) LIKE ?', [$like]);
        });
    }

    // 三态 filter（5.57，SSOT = lab-nextjs db-queries.ts:53-58；jsonb 谓词 Eloquent
    // 表达不了，参照实现走 native SQL —— rails 谓词串逐字节镜像，仅命名绑定位次改 ?）：
    //   not_yet   = 停在本环节（无 flowStatus 时 = 无流转记录新单）
    //   submitted = 已从本环节 submit 至下一环节（无 flowStatus 时 = 有流转记录且
    //               last_submitted_by 非空）
    /**
     * @return Collection<int, SampleReceipt>
     */
    private function filterThreeState(
        string $tenantId,
        ?string $contractId,
        ?string $stage,
        ?string $keyword,
        string $filter
    ): Collection {
        $contractId = (string) $contractId;
        $stage = (string) $stage;
        $keyword = (string) $keyword;
        $sql = <<<'SQL'
            (? = '' OR tenant_id = ?)
            AND (? = '' OR contract_id = ?)
            AND (? = '' OR LOWER(commission_code) LIKE '%' || LOWER(?) || '%'
                 OR LOWER(project_name) LIKE '%' || LOWER(?) || '%')
            AND (
              (? = 'not_yet' AND (
                 (? <> '' AND flow_status = ?)
                 OR (? = '' AND jsonb_array_length(flow_history) = 0)))
              OR (? = 'submitted' AND (
                 (? <> '' AND flow_status <> ?
                    AND EXISTS (SELECT 1 FROM jsonb_array_elements(flow_history) h
                                WHERE h ->> 'action' = 'submit' AND h ->> 'from' = ?))
                 OR (? = '' AND jsonb_array_length(flow_history) > 0
                       AND last_submitted_by IS NOT NULL))))
            SQL;
        $bindings = [
            $tenantId, $tenantId,
            $contractId, $contractId,
            $keyword, $keyword, $keyword,
            $filter,
            $stage, $stage, $stage,
            $filter,
            $stage, $stage, $stage, $stage,
        ];

        return SampleReceipt::query()->whereRaw($sql, $bindings)
            ->orderByDesc('updated_at')
            ->orderBy('commission_code')
            ->get();
    }
}
