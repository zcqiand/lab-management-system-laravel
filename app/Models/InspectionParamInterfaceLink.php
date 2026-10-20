<?php

declare(strict_types=1);

// M10 参数接口 link（复合 PK parameter × interface）。
// 镜像 rails app/models/inspection_param_interface_link.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionParamInterfaceLink.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $inspection_parameter_code
 * @property string $param_interface_code
 * @property string|null $report_name_code
 * @property array<string, mixed>|null $config
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionParamInterfaceLink extends Generated\InspectionParamInterfaceLink {}
