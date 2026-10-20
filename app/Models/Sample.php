<?php

declare(strict_types=1);

// M03 样品（级联于接样单；ext jsonb；id 形如 "S-<uuid>"）。
// 镜像 rails app/models/sample.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/Sample.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $id
 * @property string $receipt_id
 * @property string $sample_code
 * @property string|null $sample_name
 * @property string|null $model
 * @property string|null $specification
 * @property string|null $grade
 * @property string|null $brand
 * @property string|null $manufacturer
 * @property string|null $structural_part
 * @property string|null $represent_quantity
 * @property string|null $sample_quantity
 * @property string|null $batch_number
 * @property string|null $supply_unit
 * @property string|null $arrival_date
 * @property string|null $sampling_date
 * @property string|null $curing_condition
 * @property string|null $age
 * @property array<mixed>|null $ext
 * @property string|null $remark
 * @property string $created_at
 * @property string $updated_at
 * @property string $tenant_id
 */
class Sample extends Generated\Sample {}
