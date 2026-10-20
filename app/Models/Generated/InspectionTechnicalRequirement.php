<?php

declare(strict_types=1);

// DB-First scaffold：inspection_technical_requirements（家族 DB-First 口径；schema SSOT = shared drizzle public.inspection_technical_requirements，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/InspectionTechnicalRequirement.php extends Generated\InspectionTechnicalRequirement。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-First scaffold：inspection_technical_requirements。
 *
 * 复合主键：inspection_object_code + inspection_parameter_code + judgment_standard_code（3 列联合主键）——单键 find() 禁用，见 $primaryKey 注释。
 */
class InspectionTechnicalRequirement extends Model
{
    protected $table = 'inspection_technical_requirements';

    // Eloquent 默认 $timestamps=true 会自动 touch created_at/updated_at——lab 家族
    // 口径（镜像 rails ApplicationRecord record_timestamps=false / nowIso()）：两列是
    // TEXT 列，由应用层写 ISO-8601 UTC 串，24 表全量关自动时间戳防格式分叉。
    // 时间戳由业务代码显式写列（DB-First：created_at/updated_at 是普通列）。
    public $timestamps = false;

    // 复合主键：inspection_object_code + inspection_parameter_code + judgment_standard_code（3 列联合主键，真源=information_schema）。
    // Eloquent 不支持原生复合主键——置 null 禁用单键 find() 语义，
    // 写操作一律 where 全列显式定位；手写子类禁止覆写回单键。
    protected $primaryKey = null;

    /** @var list<string> */
    protected $fillable = [
        'inspection_object_code',
        'inspection_parameter_code',
        'judgment_standard_code',
        'conditions',
        'value_type',
        'min_value',
        'max_value',
        'target_value',
        'expression',
        'unit',
        'comparison',
        'judgment_mode',
        'verification_status',
        'clause',
        'source_page',
        'source_hash',
        'brand',
        'model',
        'grade',
        'spec',
        'sieve',
        'remark',
        'sort_order',
        'created_at',
        'updated_at',
        'tenant_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'inspection_object_code' => 'string',
        'inspection_parameter_code' => 'string',
        'judgment_standard_code' => 'string',
        'conditions' => 'string',
        'value_type' => 'string',
        'min_value' => 'int',
        'max_value' => 'int',
        'target_value' => 'string',
        'expression' => 'string',
        'unit' => 'string',
        'comparison' => 'string',
        'judgment_mode' => 'string',
        'verification_status' => 'string',
        'clause' => 'string',
        'source_page' => 'int',
        'source_hash' => 'string',
        'brand' => 'string',
        'model' => 'string',
        'grade' => 'string',
        'spec' => 'string',
        'sieve' => 'string',
        'remark' => 'string',
        'sort_order' => 'int',
        'created_at' => 'string',
        'updated_at' => 'string',
        'tenant_id' => 'string',
    ];
}
