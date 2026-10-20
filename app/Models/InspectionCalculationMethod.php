<?php

declare(strict_types=1);

// M06 计算方法（复合 PK object × parameter；表 V017 由 rules 重命名，
// PK 约束名遗留 inspection_calculation_rules_pkey）。
// 镜像 rails app/models/inspection_calculation_method.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionCalculationMethod.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionCalculationMethod extends Generated\InspectionCalculationMethod {}
