<?php

declare(strict_types=1);

// M04 标准×参数 junction（复合 PK）。
// 镜像 rails app/models/inspection_standard_parameter.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionStandardParameter.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $inspection_standard_code
 * @property string $inspection_parameter_code
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionStandardParameter extends Generated\InspectionStandardParameter {}
