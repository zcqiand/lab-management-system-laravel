<?php

declare(strict_types=1);

// M04 检测专业（全局字典，无租户列）。表 inspection_specialties，PK code。
// 镜像 rails app/models/inspection_specialty.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionSpecialty.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionSpecialty extends Generated\InspectionSpecialty {}
