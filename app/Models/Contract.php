<?php

declare(strict_types=1);

// M02 合同（tenant 隔离，unique (tenant_id, contract_code)；id 形如 "C-<uuid>"）。
// 镜像 rails app/models/contract.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/Contract.php（scaffold 生成物，禁手改）。

namespace App\Models;

/**
 * @property string $id
 * @property string $contract_code
 * @property string|null $client_unit
 * @property string|null $project_name
 * @property string|null $project_location
 * @property string|null $construction_unit
 * @property string|null $inspection_specialty_code
 * @property string|null $building_unit
 * @property string|null $supervisor_unit
 * @property string|null $inspection_person
 * @property string|null $inspection_phone
 * @property string|null $witness_unit
 * @property string|null $witness
 * @property string|null $witness_phone
 * @property string|null $contact_person
 * @property string|null $contact_phone
 * @property string|null $entrusted_date
 * @property string $status
 * @property string $created_at
 * @property string $updated_at
 * @property string $tenant_id
 */
class Contract extends Generated\Contract {}
