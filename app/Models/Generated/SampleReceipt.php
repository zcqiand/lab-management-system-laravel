<?php

declare(strict_types=1);

// DB-First scaffold：sample_receipts（家族 DB-First 口径；schema SSOT = shared drizzle public.sample_receipts，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/SampleReceipt.php extends Generated\SampleReceipt。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-First scaffold：sample_receipts。
 */
class SampleReceipt extends Model
{
    protected $table = 'sample_receipts';

    // Eloquent 默认 $timestamps=true 会自动 touch created_at/updated_at——lab 家族
    // 口径（镜像 rails ApplicationRecord record_timestamps=false / nowIso()）：两列是
    // TEXT 列，由应用层写 ISO-8601 UTC 串，24 表全量关自动时间戳防格式分叉。
    // 时间戳由业务代码显式写列（DB-First：created_at/updated_at 是普通列）。
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    /** @var list<string> */
    protected $fillable = [
        'id',
        'contract_id',
        'commission_code',
        'commission_date',
        'commission_register_code',
        'commission_register_date',
        'category_code',
        'project_name',
        'client_unit',
        'building_unit',
        'supervisor_unit',
        'construction_unit',
        'witness_unit',
        'sampling_location',
        'witness',
        'witness_phone',
        'inspector',
        'inspector_phone',
        'received_by',
        'sample_source',
        'test_category',
        'test_environment',
        'main_equipment',
        'test_operator',
        'test_start_date',
        'test_end_date',
        'original_record_no',
        'remark',
        'judgment_basis',
        'testing_basis',
        'test_parameters',
        'flow_status',
        'flow_history',
        'last_submitted_by',
        'assignee_id',
        'assignee_name',
        'planned_test_date',
        'report_code',
        'report_date',
        'conclusion',
        'result',
        'issued_at',
        'created_at',
        'updated_at',
        'tenant_id',
        'ext',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'string',
        'contract_id' => 'string',
        'commission_code' => 'string',
        'commission_date' => 'string',
        'commission_register_code' => 'string',
        'commission_register_date' => 'string',
        'category_code' => 'string',
        'project_name' => 'string',
        'client_unit' => 'string',
        'building_unit' => 'string',
        'supervisor_unit' => 'string',
        'construction_unit' => 'string',
        'witness_unit' => 'string',
        'sampling_location' => 'string',
        'witness' => 'string',
        'witness_phone' => 'string',
        'inspector' => 'string',
        'inspector_phone' => 'string',
        'received_by' => 'string',
        'sample_source' => 'string',
        'test_category' => 'string',
        'test_environment' => 'string',
        'main_equipment' => 'string',
        'test_operator' => 'string',
        'test_start_date' => 'string',
        'test_end_date' => 'string',
        'original_record_no' => 'string',
        'remark' => 'string',
        'judgment_basis' => 'array',
        'testing_basis' => 'array',
        'test_parameters' => 'array',
        'flow_status' => 'string',
        'flow_history' => 'array',
        'last_submitted_by' => 'string',
        'assignee_id' => 'string',
        'assignee_name' => 'string',
        'planned_test_date' => 'string',
        'report_code' => 'string',
        'report_date' => 'string',
        'conclusion' => 'string',
        'result' => 'string',
        'issued_at' => 'datetime',
        'created_at' => 'string',
        'updated_at' => 'string',
        'tenant_id' => 'string',
        'ext' => 'array',
    ];
}
