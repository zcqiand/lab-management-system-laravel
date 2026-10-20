<?php

declare(strict_types=1);

// M05 报告名×标准 junction（三元复合 PK：code × standard × role）。
// 镜像 rails app/models/inspection_report_name_standard.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionReportNameStandard.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $report_name_code
 * @property string $inspection_standard_code
 * @property string $role
 * @property string|null $remark
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionReportNameStandard extends Generated\InspectionReportNameStandard {}
