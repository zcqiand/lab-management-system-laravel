<?php

declare(strict_types=1);

// M03 样品（级联于接样单；ext jsonb；id 形如 "S-<uuid>"）。
// 镜像 rails app/models/sample.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/Sample.php（scaffold 生成物，禁手改）。

namespace App\Models;

class Sample extends Generated\Sample {}
