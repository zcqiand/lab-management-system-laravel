<?php

declare(strict_types=1);

// M04 对象×参数 junction（复合 PK，含 qualification_level）。
// 镜像 rails app/models/inspection_object_parameter.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionObjectParameter.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionObjectParameter extends Generated\InspectionObjectParameter {}
