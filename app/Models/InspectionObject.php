<?php

declare(strict_types=1);

// M04 检测对象（全局字典）。表 inspection_objects，PK code。
// 镜像 rails app/models/inspection_object.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionObject.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $code
 * @property string $inspection_specialty_code
 * @property string $source_project_no
 * @property string $source_project_name
 * @property string $name
 * @property bool $is_optional_for_qualification
 * @property bool $is_official
 * @property bool $enabled
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionObject extends Generated\InspectionObject {}
