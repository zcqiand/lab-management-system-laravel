<?php

declare(strict_types=1);

// M10 参数接口（全局；PK 约束名遗留 param_interfaces_pkey）。
// 镜像 rails app/models/inspection_param_interface.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionParamInterface.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $code
 * @property string|null $name
 * @property string $component_path
 * @property string|null $description
 * @property bool|null $is_official
 * @property int $sort_order
 * @property array<string, mixed>|null $config
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionParamInterface extends Generated\InspectionParamInterface {}
