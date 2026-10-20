<?php

declare(strict_types=1);

// M04 专业×对象 junction（复合 PK）。
// 镜像 rails app/models/inspection_specialty_object.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionSpecialtyObject.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionSpecialtyObject extends Generated\InspectionSpecialtyObject {}
