<?php

declare(strict_types=1);

// M03 接样单（tenant 隔离；flow_status 7 态机 + flow_history jsonb；id 形如 "R-<uuid>"）。
// 镜像 rails app/models/sample_receipt.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/SampleReceipt.php（scaffold 生成物，禁手改）。

namespace App\Models;

class SampleReceipt extends Generated\SampleReceipt {}
