<?php

declare(strict_types=1);

// M05 对象×报告名 junction（复合 PK）。
// 镜像 rails app/models/inspection_object_report_name.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionObjectReportName.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $inspection_object_code
 * @property string $report_name_code
 * @property string|null $remark
 * @property string $created_at
 * @property string $updated_at
 */
class InspectionObjectReportName extends Generated\InspectionObjectReportName {}
