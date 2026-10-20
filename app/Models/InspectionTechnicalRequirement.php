<?php

declare(strict_types=1);

// M04.F05 技术要求（三元复合 PK：object × parameter × judgment_standard；tenant 隔离）。
// 镜像 rails app/models/inspection_technical_requirement.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionTechnicalRequirement.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionTechnicalRequirement extends Generated\InspectionTechnicalRequirement {}
