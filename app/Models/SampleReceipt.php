<?php

declare(strict_types=1);

// M03 接样单（tenant 隔离；flow_status 7 态机 + flow_history jsonb；id 形如 "R-<uuid>"）。
// 镜像 rails app/models/sample_receipt.rb（lab laravel 全量复刻批；薄包装——常量/关系/作用域
// 仅在 rails 侧确有时叠加，禁发明语义）。表/PK/fillable/casts 真源 =
// app/Models/Generated/SampleReceipt.php（scaffold 生成物，禁手改）。

namespace App\Models;

use Carbon\CarbonInterface;

/**
 * @property string $id
 * @property string $contract_id
 * @property string $commission_code
 * @property string|null $commission_date
 * @property string|null $commission_register_code
 * @property string|null $commission_register_date
 * @property string $category_code
 * @property string|null $project_name
 * @property string|null $client_unit
 * @property string|null $building_unit
 * @property string|null $supervisor_unit
 * @property string|null $construction_unit
 * @property string|null $witness_unit
 * @property string|null $sampling_location
 * @property string|null $witness
 * @property string|null $witness_phone
 * @property string|null $inspector
 * @property string|null $inspector_phone
 * @property string $received_by
 * @property string|null $sample_source
 * @property string|null $test_category
 * @property string|null $test_environment
 * @property string|null $main_equipment
 * @property string|null $test_operator
 * @property string|null $test_start_date
 * @property string|null $test_end_date
 * @property string|null $original_record_no
 * @property string|null $remark
 * @property array<mixed>|null $judgment_basis
 * @property array<mixed>|null $testing_basis
 * @property array<mixed>|null $test_parameters
 * @property string $flow_status
 * @property array<mixed>|null $flow_history
 * @property string|null $last_submitted_by
 * @property string|null $assignee_id
 * @property string|null $assignee_name
 * @property string|null $planned_test_date
 * @property string|null $report_code
 * @property string|null $report_date
 * @property string|null $conclusion
 * @property string|null $result
 * @property CarbonInterface|null $issued_at
 * @property string $created_at
 * @property string $updated_at
 * @property string $tenant_id
 * @property array<mixed>|null $ext
 */
class SampleReceipt extends Generated\SampleReceipt {}
