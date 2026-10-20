<?php

declare(strict_types=1);

// M05 报告名称（全局字典）。
// 镜像 rails app/models/inspection_report_name.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionReportName.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $code
 * @property string $name
 * @property string|null $full_name
 * @property string|null $template_path
 * @property string|null $summary_name
 * @property array<int, mixed>|null $ext_fields
 * @property string|null $description
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionReportName extends Generated\InspectionReportName {}
