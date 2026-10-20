<?php

declare(strict_types=1);

// DB-First scaffold：inspection_calculation_methods（家族 DB-First 口径；schema SSOT = shared drizzle public.inspection_calculation_methods，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/InspectionCalculationMethod.php extends Generated\InspectionCalculationMethod。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-First scaffold：inspection_calculation_methods。
 *
 * 复合主键：inspection_object_code + inspection_parameter_code（2 列联合主键）——单键 find() 禁用，见 $primaryKey 注释。
 */
class InspectionCalculationMethod extends Model
{
    protected $table = 'inspection_calculation_methods';

    // Eloquent 默认 $timestamps=true 会自动 touch created_at/updated_at——lab 家族
    // 口径（镜像 rails ApplicationRecord record_timestamps=false / nowIso()）：两列是
    // TEXT 列，由应用层写 ISO-8601 UTC 串，24 表全量关自动时间戳防格式分叉。
    // 时间戳由业务代码显式写列（DB-First：created_at/updated_at 是普通列）。
    public $timestamps = false;

    // 复合主键：inspection_object_code + inspection_parameter_code（2 列联合主键，真源=information_schema）。
    // Eloquent 不支持原生复合主键——置 null 禁用单键 find() 语义，
    // 写操作一律 where 全列显式定位；手写子类禁止覆写回单键。
    protected $primaryKey = null;

    /** @var list<string> */
    protected $fillable = [
        'inspection_object_code',
        'inspection_parameter_code',
        'testing_standard_code',
        'report_name_code',
        'algorithm_type',
        'specimen_count',
        'formula',
        'conditions',
        'rounding_rule',
        'remark',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'inspection_object_code' => 'string',
        'inspection_parameter_code' => 'string',
        'testing_standard_code' => 'string',
        'report_name_code' => 'string',
        'algorithm_type' => 'string',
        'specimen_count' => 'int',
        'formula' => 'string',
        'conditions' => 'string',
        'rounding_rule' => 'string',
        'remark' => 'string',
        'sort_order' => 'int',
        'created_at' => 'string',
        'updated_at' => 'string',
    ];
}
