<?php

declare(strict_types=1);

// M04 检测对象字典：model（tenant 隔离，unique (tenant_id, code) —— code 是全局
// PK 列但家族约定按 tenant 过滤后寻址，镜像 lab-springboot CatalogService）。
// 镜像 rails app/models/inspection_model.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/InspectionModel.php（scaffold 生成物，禁手改）。

namespace App\Models;

class InspectionModel extends Generated\InspectionModel {}
