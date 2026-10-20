<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

// M02.F01 合同域（lab-springboot ContractService/ContractMapper 镜像 → rails
// Contracts::ContractService 的 Laravel 同构移植）。
// tenant-scoped（V012）；keyword 过滤 contract_code/project_name；status ∈ {active, archived}；
// 排序 updatedAt DESC, contractCode（repo.filter JPQL 镜像）；id 形如 "C-<uuid>"；
// created_at/updated_at 由应用层写 ISO 串（Time::nowIso，rails now_iso 同源）。
// ModelNotFoundException 由消费方（BusinessEnvelope 语义）catch 转 404，禁落基座 UUID 分叉。
final class ContractService
{
    public const STATUSES = ['active', 'archived'];

    private const DEFAULT_STATUS = 'active';

    // fromCreate 全字段（contractCode 含内）；applyUpdate 不碰 contractCode。
    // key = 契约 camelCase 入参，value = DB snake_case 列（rails f.underscore 镜像）。
    private const UPDATE_FIELDS = [
        'clientUnit' => 'client_unit',
        'projectName' => 'project_name',
        'projectLocation' => 'project_location',
        'constructionUnit' => 'construction_unit',
        'inspectionSpecialtyCode' => 'inspection_specialty_code',
        'buildingUnit' => 'building_unit',
        'supervisorUnit' => 'supervisor_unit',
        'inspectionPerson' => 'inspection_person',
        'inspectionPhone' => 'inspection_phone',
        'witnessUnit' => 'witness_unit',
        'witness' => 'witness',
        'witnessPhone' => 'witness_phone',
        'contactPerson' => 'contact_person',
        'contactPhone' => 'contact_phone',
        'entrustedDate' => 'entrusted_date',
        'status' => 'status',
    ];

    private const CREATE_FIELDS = ['contractCode' => 'contract_code'] + self::UPDATE_FIELDS;

    public function __construct(private readonly string $tenantId) {}

    public function list(?string $keyword, ?string $status): array
    {
        $query = Contract::query()->where('tenant_id', $this->tenantId);
        $needle = is_string($keyword) ? mb_strtolower($keyword) : '';
        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(static function ($q) use ($like): void {
                // rails LOWER(contract_code) LIKE :k OR LOWER(project_name) LIKE :k 镜像
                $q->whereRaw('LOWER(contract_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(project_name) LIKE ?', [$like]);
            });
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        $query->orderByDesc('updated_at')->orderBy('contract_code');

        return $query->get()
            ->map(fn (Contract $e): array => $this->dto($e))
            ->all();
    }

    public function get(string $id): array
    {
        return $this->dto($this->find($id));
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function create(array $body): array
    {
        $now = Time::nowIso();
        $attrs = $this->fieldAttrs($body, self::CREATE_FIELDS);
        $attrs['id'] = $this->newId();
        $attrs['tenant_id'] = $this->tenantId;
        $attrs['status'] = $this->statusOf($body);
        $attrs['created_at'] = $now;
        $attrs['updated_at'] = $now;

        $contract = new Contract($attrs);
        // NOT NULL / unique(tenant_id, contract_code) 违反抛 QueryException →
        // 基座映射 400 "constraint violation: …"（rails create! → RecordInvalid 同形）
        $contract->save();

        return $this->dto($contract);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function update(string $id, array $body): array
    {
        $contract = $this->find($id);
        // 镜像 apply_update：null 字段跳过（部分更新），updated_at 恒刷新
        foreach (self::UPDATE_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $contract->{$column} = $value;
            }
        }
        $contract->updated_at = Time::nowIso();
        $contract->save();

        return $this->dto($contract);
    }

    public function delete(string $id): void
    {
        $this->find($id)->delete();
    }

    private function find(string $id): Contract
    {
        $contract = Contract::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $id)
            ->first();
        if ($contract === null) {
            throw (new ModelNotFoundException)->setModel(Contract::class, [$id]);
        }

        return $contract;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function statusOf(array $body): string
    {
        $status = $body['status'] ?? null;
        if ($status === null) {
            return self::DEFAULT_STATUS;
        }
        if (! is_string($status) || ! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unexpected value '{$status}'");
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    private function fieldAttrs(array $body, array $fields): array
    {
        $attrs = [];
        foreach ($fields as $camel => $column) {
            $attrs[$column] = $body[$camel] ?? null;
        }

        return $attrs;
    }

    private function newId(): string
    {
        return 'C-'.Str::uuid()->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(Contract $e): array
    {
        return [
            'id' => $e->id,
            'contract_code' => $e->contract_code,
            'client_unit' => $e->client_unit,
            'project_name' => $e->project_name,
            'project_location' => $e->project_location,
            'construction_unit' => $e->construction_unit,
            'inspection_specialty_code' => $e->inspection_specialty_code,
            'building_unit' => $e->building_unit,
            'supervisor_unit' => $e->supervisor_unit,
            'inspection_person' => $e->inspection_person,
            'inspection_phone' => $e->inspection_phone,
            'witness_unit' => $e->witness_unit,
            'witness' => $e->witness,
            'witness_phone' => $e->witness_phone,
            'contact_person' => $e->contact_person,
            'contact_phone' => $e->contact_phone,
            'entrusted_date' => $e->entrusted_date,
            'status' => $e->status,
            'tenant_id' => $e->tenant_id,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
