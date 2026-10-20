<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Models\SampleReceipt;
use App\Support\Time;
use Illuminate\Support\Str;

// 接样单 DTO 与 camel→snake 字段映射（lab-springboot SampleReceiptMapper 镜像 →
// rails Receipts::ReceiptFields 同构移植；逐字段映射的密度是移植义务，从
// SampleReceiptService 拆出以守 Metrics 门）。
trait ReceiptFields
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $fields  camelCase 契约键 → snake_case 列
     * @return array<string, mixed>
     */
    private function fieldAttrs(array $body, array $fields): array
    {
        $attrs = [];
        foreach ($fields as $camel => $column) {
            $attrs[$column] = $body[$camel] ?? null;
        }

        return $attrs;
    }

    private function newId(): string
    {
        return 'R-'.Str::uuid()->toString();
    }

    private function nowIso(): string
    {
        return Time::nowIso();
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(SampleReceipt $e): array
    {
        return array_merge($this->dtoCommission($e), $this->dtoUnits($e), $this->dtoFlow($e));
    }

    /**
     * @return array<string, mixed>
     */
    private function dtoCommission(SampleReceipt $e): array
    {
        return [
            'id' => $e->id,
            'contract_id' => $e->contract_id,
            'commission_code' => $e->commission_code,
            'commission_date' => $e->commission_date,
            'commission_register_code' => $e->commission_register_code,
            'commission_register_date' => $e->commission_register_date,
            'category_code' => $e->category_code,
            'project_name' => $e->project_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dtoUnits(SampleReceipt $e): array
    {
        return [
            'client_unit' => $e->client_unit,
            'building_unit' => $e->building_unit,
            'supervisor_unit' => $e->supervisor_unit,
            'construction_unit' => $e->construction_unit,
            'witness_unit' => $e->witness_unit,
            'sampling_location' => $e->sampling_location,
            'witness' => $e->witness,
            'witness_phone' => $e->witness_phone,
            'inspector' => $e->inspector,
            'inspector_phone' => $e->inspector_phone,
            'received_by' => $e->received_by,
            'sample_source' => $e->sample_source,
            'test_category' => $e->test_category,
            'test_environment' => $e->test_environment,
            'main_equipment' => $e->main_equipment,
            'test_operator' => $e->test_operator,
            'test_start_date' => $e->test_start_date,
            'test_end_date' => $e->test_end_date,
            'original_record_no' => $e->original_record_no,
            'remark' => $e->remark,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dtoFlow(SampleReceipt $e): array
    {
        return [
            'judgment_basis' => $e->judgment_basis ?? [],
            'testing_basis' => $e->testing_basis ?? [],
            'test_parameters' => $e->test_parameters ?? [],
            'flow_status' => $e->flow_status,
            'flow_history' => $e->flow_history ?? [],
            'last_submitted_by' => $e->last_submitted_by,
            'assignee_id' => $e->assignee_id,
            'assignee_name' => $e->assignee_name,
            'planned_test_date' => $e->planned_test_date,
            'report_code' => $e->report_code,
            'report_date' => $e->report_date,
            'conclusion' => $e->conclusion,
            'result' => $e->result,
            // rails issued_at&.iso8601 镜像（datetime cast → Carbon，ISO-8601 带偏移）
            'issued_at' => $e->issued_at?->format(DATE_ATOM),
            'tenant_id' => $e->tenant_id,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
