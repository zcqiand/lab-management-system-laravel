<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Models\Contract;
use App\Models\SampleReceipt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M03.F01-F09 接样单域（lab-springboot SampleReceiptService/SampleReceiptMapper 镜像 →
// rails Receipts::SampleReceiptService 的 Laravel 同构移植）。
// tenant-scoped；contract FK 显式校验（NSEE→404）；flow_status 7+1 态机 + flow_history
// jsonb 追加；list 支持 5.57 三态 filter（not_yet/submitted，jsonb 谓词 native SQL）。
final class SampleReceiptService
{
    use ReceiptFields;
    use ReceiptQueries;

    public const FLOW_STATUSES = [
        'receiving', 'task_assignment', 'data_entry', 'review', 'approval', 'issuance',
        'archived', 'completed',
    ];

    // rails LIST_FIELDS camel→snake 逐字段映射（field_attrs + f.underscore 同义）
    private const LIST_FIELDS = [
        'commissionCode' => 'commission_code',
        'commissionDate' => 'commission_date',
        'commissionRegisterCode' => 'commission_register_code',
        'commissionRegisterDate' => 'commission_register_date',
        'categoryCode' => 'category_code',
        'projectName' => 'project_name',
        'clientUnit' => 'client_unit',
        'buildingUnit' => 'building_unit',
        'supervisorUnit' => 'supervisor_unit',
        'constructionUnit' => 'construction_unit',
        'witnessUnit' => 'witness_unit',
        'samplingLocation' => 'sampling_location',
        'witness' => 'witness',
        'witnessPhone' => 'witness_phone',
        'inspector' => 'inspector',
        'inspectorPhone' => 'inspector_phone',
        'receivedBy' => 'received_by',
        'sampleSource' => 'sample_source',
        'testCategory' => 'test_category',
        'testEnvironment' => 'test_environment',
        'mainEquipment' => 'main_equipment',
        'testOperator' => 'test_operator',
        'testStartDate' => 'test_start_date',
        'testEndDate' => 'test_end_date',
        'originalRecordNo' => 'original_record_no',
        'remark' => 'remark',
    ];

    // rails %w[contractId] + LIST_FIELDS（列表拼接，键不重叠，array_merge 同义）
    private const CREATE_FIELDS = ['contractId' => 'contract_id'] + self::LIST_FIELDS;

    private const JSON_LIST_FIELDS = [
        'judgmentBasis' => 'judgment_basis',
        'testingBasis' => 'testing_basis',
        'testParameters' => 'test_parameters',
    ];

    private const ASSIGN_TASK_FIELDS = [
        'assigneeId' => 'assignee_id',
        'assigneeName' => 'assignee_name',
        'plannedTestDate' => 'planned_test_date',
    ];

    public function __construct(private readonly string $tenantId) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $contractId, ?string $flowStatus, ?string $keyword, ?string $filter): array
    {
        if ($filter === 'not_yet' || $filter === 'submitted') {
            return $this->filterThreeState($this->tenantId, $contractId, $flowStatus, $keyword, $filter)
                ->map(fn (SampleReceipt $e): array => $this->dto($e))
                ->values()
                ->all();
        }

        $query = SampleReceipt::query()->where('tenant_id', $this->tenantId);
        if ($contractId !== null && $contractId !== '') {
            $query->where('contract_id', $contractId);
        }
        if ($flowStatus !== null) {
            $query->where('flow_status', $flowStatus);
        }
        $this->filterKeyword($query, $keyword);

        return $query->orderByDesc('updated_at')
            ->orderBy('commission_code')
            ->get()
            ->map(fn (SampleReceipt $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->dto($this->find($id));
    }

    /**
     * @return list<mixed>
     */
    public function history(string $id): array
    {
        $flowHistory = $this->find($id)->flow_history;
        $flowHistory = is_array($flowHistory) ? $flowHistory : [];

        return $flowHistory;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        // contract FK 显式校验（NSEE→404，rails Contract.exists? 语义；消息逐字
        // 「Contract not found: <id>」，controller catch ModelNotFoundException 渲染）
        $contractId = $body['contractId'] ?? null;
        if (! is_string($contractId)) {
            $contractId = null;
        }
        $exists = Contract::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $contractId)
            ->exists();
        if (! $exists) {
            throw (new ModelNotFoundException)->setModel(Contract::class, [(string) $contractId]);
        }

        $now = $this->nowIso();
        $attrs = $this->fieldAttrs($body, self::CREATE_FIELDS);
        $attrs['id'] = $this->newId();
        $attrs['tenant_id'] = $this->tenantId;
        $attrs['flow_status'] = 'receiving';
        $attrs['flow_history'] = [];
        $attrs['result'] = '';
        $attrs['created_at'] = $now;
        $attrs['updated_at'] = $now;

        $receipt = new SampleReceipt($attrs);
        // NOT NULL / FK（category_code → inspection_report_names）违反抛 QueryException
        // → 基座映射 400 "constraint violation: …"（rails create! 同形）
        $receipt->save();

        return $this->dto($receipt);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        $receipt = $this->find($id);
        // 镜像 field_attrs(...).compact.each：null 字段跳过（部分更新）
        foreach (self::LIST_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $receipt->{$column} = $value;
            }
        }
        // 镜像 JSON_LIST_FIELDS：v 非 nil 才写，Array(v || [])（nil/false → []，标量包数组）
        foreach (self::JSON_LIST_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value === null) {
                continue;
            }
            $receipt->{$column} = $this->toArrayLike($value);
        }
        $receipt->updated_at = $this->nowIso();
        $receipt->save();

        return $this->dto($receipt);
    }

    public function delete(string $id): void
    {
        $this->find($id)->delete();
    }

    // M03.F02 任务分配：字段按需更新；仅 receiving 阶段推进 task_assignment 并写 history
    // （action 字面量 "submit" 是真 submit 转移，operator 记 assigneeName —— 参照实现）
    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function assignTask(string $id, array $body): array
    {
        $receipt = $this->find($id);
        foreach (self::ASSIGN_TASK_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $receipt->{$column} = $value;
            }
        }
        if ($receipt->flow_status === 'receiving') {
            $receipt->flow_status = 'task_assignment';
            $this->appendHistory($receipt, [
                'action' => 'submit',
                'operator' => $body['assigneeName'] ?? null,
                'from' => 'receiving',
                'to' => 'task_assignment',
                'reason' => 'M03.F02 任务分配',
            ]);
        }
        $receipt->updated_at = $this->nowIso();
        $receipt->save();

        return $this->dto($receipt);
    }

    // M03.F06 等阶段推进：状态迁移 + last_submitted_by 语义（5.69：submit 写操作人 /
    // withdraw 清空 / return 保留）+ history 记 action 真值（5.75）
    /**
     * @return array<string, mixed>
     */
    public function transitionTo(
        string $id,
        string $from,
        string $to,
        string $action,
        ?string $operator,
        ?string $reason
    ): array {
        $receipt = $this->find($id);
        if ($receipt->flow_status !== $from) {
            throw new InvalidArgumentException(
                "Receipt {$id} not in expected stage {$from} but {$receipt->flow_status}"
            );
        }

        $receipt->flow_status = $to;
        if ($action === 'submit') {
            $receipt->last_submitted_by = $operator;
        }
        if ($action === 'withdraw') {
            $receipt->last_submitted_by = null;
        }
        $this->appendHistory($receipt, [
            'action' => $action,
            'from' => $from,
            'to' => $to,
            'operator' => $operator,
            'reason' => $reason,
        ]);
        $receipt->updated_at = $this->nowIso();
        $receipt->save();

        return $this->dto($receipt);
    }

    // flow_history 追加（entry 键集 action/from/to/operator/reason + at，jsonb 回读
    // 是 string 键；rails merge('at' => now_iso) 镜像 —— merge 语义 array_merge）
    /**
     * @param  array<string, mixed>  $entry
     */
    private function appendHistory(SampleReceipt $receipt, array $entry): void
    {
        $entry['at'] = $this->nowIso();
        $flowHistory = $receipt->flow_history;
        $receipt->flow_history = array_merge(is_array($flowHistory) ? $flowHistory : [], [$entry]);
    }

    // Ruby Array(v || []) 镜像：nil/false → []，标量包单元素数组，数组原样
    /**
     * @return list<mixed>
     */
    private function toArrayLike(mixed $value): array
    {
        if (! is_array($value)) {
            return $value === false ? [] : [$value];
        }

        return array_values($value);
    }

    private function find(string $id): SampleReceipt
    {
        $receipt = SampleReceipt::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $id)
            ->first();
        if ($receipt === null) {
            throw (new ModelNotFoundException)->setModel(SampleReceipt::class, [$id]);
        }

        return $receipt;
    }
}
