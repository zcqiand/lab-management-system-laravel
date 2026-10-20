<?php

declare(strict_types=1);

// M03 检测记录（级联于样品；verdict 判定；id 形如 "TR-<uuid>"）。
// 镜像 rails app/models/test_record.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/TestRecord.php（scaffold 生成物，禁手改）。

namespace App\Models;

class TestRecord extends Generated\TestRecord {}
