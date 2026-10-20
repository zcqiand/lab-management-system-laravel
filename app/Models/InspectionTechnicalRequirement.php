<?php

declare(strict_types=1);

// M04.F05 技术要求（三元复合 PK：object × parameter × judgment_standard；tenant 隔离）。
// 镜像 rails app/models/inspection_technical_requirement.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionTechnicalRequirement.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $inspection_object_code
 * @property string $inspection_parameter_code
 * @property string $judgment_standard_code
 * @property string|null $conditions
 * @property string $value_type
 * @property int|null $min_value
 * @property int|null $max_value
 * @property string|null $target_value
 * @property string|null $expression
 * @property string|null $unit
 * @property string $comparison
 * @property string $judgment_mode
 * @property string $verification_status
 * @property string|null $clause
 * @property int|null $source_page
 * @property string|null $source_hash
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $grade
 * @property string|null $spec
 * @property string|null $sieve
 * @property string|null $remark
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 * @property string $tenant_id
 */
class InspectionTechnicalRequirement extends Generated\InspectionTechnicalRequirement {}
