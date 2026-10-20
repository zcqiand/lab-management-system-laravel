<?php

declare(strict_types=1);

// DB-First scaffold：samples（家族 DB-First 口径；schema SSOT = shared drizzle public.samples，
// 本仓禁 migrate/seed）。由 scripts/scaffold-models.mjs 从 lab_dev 真库 information_schema 反推生成——
// 禁手改，L4.codegen.idempotent strict 复跑字节比对会暴露手改。手写业务逻辑（常量 / 关系 / 领域方法）
// 写在 app/Models/Sample.php extends Generated\Sample。
//
// 字段含义见 lab-management-system-shared/src/db/schema.ts。

namespace App\Models\Generated;

use Illuminate\Database\Eloquent\Model;

/**
 * DB-First scaffold：samples。
 */
class Sample extends Model
{
    protected $table = 'samples';

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
        'receipt_id',
        'sample_code',
        'sample_name',
        'model',
        'specification',
        'grade',
        'brand',
        'manufacturer',
        'structural_part',
        'represent_quantity',
        'sample_quantity',
        'batch_number',
        'supply_unit',
        'arrival_date',
        'sampling_date',
        'curing_condition',
        'age',
        'ext',
        'remark',
        'created_at',
        'updated_at',
        'tenant_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'string',
        'receipt_id' => 'string',
        'sample_code' => 'string',
        'sample_name' => 'string',
        'model' => 'string',
        'specification' => 'string',
        'grade' => 'string',
        'brand' => 'string',
        'manufacturer' => 'string',
        'structural_part' => 'string',
        'represent_quantity' => 'string',
        'sample_quantity' => 'string',
        'batch_number' => 'string',
        'supply_unit' => 'string',
        'arrival_date' => 'string',
        'sampling_date' => 'string',
        'curing_condition' => 'string',
        'age' => 'string',
        'ext' => 'array',
        'remark' => 'string',
        'created_at' => 'string',
        'updated_at' => 'string',
        'tenant_id' => 'string',
    ];
}
