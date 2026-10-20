<?php

declare(strict_types=1);

// DB-First scaffold：inspection_objects（家族 DB-First 口径；schema SSOT = shared drizzle public.inspection_objects，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/InspectionObject.php extends Generated\InspectionObject。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-First scaffold：inspection_objects。
 */
class InspectionObject extends Model
{
    protected $table = 'inspection_objects';

    // Eloquent 默认 $timestamps=true 会自动 touch created_at/updated_at——lab 家族
    // 口径（镜像 rails ApplicationRecord record_timestamps=false / nowIso()）：两列是
    // TEXT 列，由应用层写 ISO-8601 UTC 串，24 表全量关自动时间戳防格式分叉。
    // 时间戳由业务代码显式写列（DB-First：created_at/updated_at 是普通列）。
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'code';

    /** @var list<string> */
    protected $fillable = [
        'code',
        'inspection_specialty_code',
        'source_project_no',
        'source_project_name',
        'name',
        'is_optional_for_qualification',
        'is_official',
        'enabled',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'code' => 'string',
        'inspection_specialty_code' => 'string',
        'source_project_no' => 'string',
        'source_project_name' => 'string',
        'name' => 'string',
        'is_optional_for_qualification' => 'bool',
        'is_official' => 'bool',
        'enabled' => 'bool',
        'sort_order' => 'int',
        'created_at' => 'string',
        'updated_at' => 'string',
    ];
}
