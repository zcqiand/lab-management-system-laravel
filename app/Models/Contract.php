<?php

declare(strict_types=1);

// M02 合同（tenant 隔离，unique (tenant_id, contract_code)；id 形如 "C-<uuid>"）。
// 镜像 rails app/models/contract.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/Contract.php（scaffold 生成物，禁手改）。

namespace App\Models;

class Contract extends Generated\Contract {}
