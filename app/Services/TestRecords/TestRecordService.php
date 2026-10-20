<?php

declare(strict_types=1);

namespace App\Services\TestRecords;

use App\Models\TestRecord;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

// M03.F03 检测记录数据录入域（lab-springboot TestRecordService/TestRecordMapper 镜像 →
// rails TestRecords::TestRecordService 的 Laravel 同构移植）。
// tenant-scoped；create 四必填（IAE→400）；sample/parameter FK 走 DB 约束（QueryException
// → 基座 400 "constraint violation"——与 receipts/samples 的显式查 404 是有意分叉，勿统一）；
// 排序 updated_at DESC, id。
final class TestRecordService
{
    // rails UPDATE_FIELDS camel→snake 逐字段映射（field_attrs + f.underscore 同义）
    private const UPDATE_FIELDS = [
        'parameterCode' => 'parameter_code',
        'standardCode' => 'standard_code',
        'requirementCode' => 'requirement_code',
        'requirement' => 'requirement',
        'result' => 'result',
        'verdict' => 'verdict',
    ];

    // rails %w[sampleId] + UPDATE_FIELDS（列表拼接，键不重叠）
    private const CREATE_FIELDS = [
        'sampleId' => 'sample_id',
    ] + self::UPDATE_FIELDS;

    public function __construct(private readonly string $tenantId) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $sampleId): array
    {
        $query = TestRecord::query()->where('tenant_id', $this->tenantId);
        if ($sampleId !== null && $sampleId !== '') {
            $query->where('sample_id', $sampleId);
        }

        return $query->orderByDesc('updated_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TestRecord $e): array => $this->dto($e))
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
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        // rails 四必填 select { |f| req[f].nil? } 镜像：缺任一 → IAE（基座映射 400）
        $missing = [];
        foreach (['sampleId', 'parameterCode', 'requirement', 'result'] as $field) {
            if (($body[$field] ?? null) === null) {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            throw new InvalidArgumentException('sampleId, parameterCode, requirement and result are required');
        }

        $now = $this->nowIso();
        $attrs = $this->fieldAttrs($body, self::CREATE_FIELDS);
        $attrs['id'] = $this->newId();
        $attrs['tenant_id'] = $this->tenantId;
        $attrs['created_at'] = $now;
        $attrs['updated_at'] = $now;

        $record = new TestRecord($attrs);
        // sample/parameter FK 不显式查——违反抛 QueryException（23503）→ 基座映射
        // 400 "constraint violation: …"（rails create! 同形）
        $record->save();

        return $this->dto($record);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        $record = $this->find($id);
        // 镜像 field_attrs(...).compact.each：null 字段跳过（部分更新；verdict 置 null
        // 走 setVerdict 直写口）
        foreach (self::UPDATE_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $record->{$column} = $value;
            }
        }
        $record->updated_at = $this->nowIso();
        $record->save();

        return $this->dto($record);
    }

    // 人工改判 verdict（M03.F05/F06 报告流程可能触发）；verdict 直写（可置 null，strict 口径）
    public function setVerdict(string $id, ?string $verdict): array
    {
        $record = $this->find($id);
        $record->verdict = $verdict;
        $record->updated_at = $this->nowIso();
        $record->save();

        return $this->dto($record);
    }

    public function delete(string $id): void
    {
        $this->find($id)->delete();
    }

    private function find(string $id): TestRecord
    {
        $record = TestRecord::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $id)
            ->first();
        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(TestRecord::class, [$id]);
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $fields  camelCase 契约键 → snake_case 列
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
        return 'TR-'.Str::uuid()->toString();
    }

    private function nowIso(): string
    {
        return Time::nowIso();
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(TestRecord $e): array
    {
        return [
            'id' => $e->id,
            'tenant_id' => $e->tenant_id,
            'sample_id' => $e->sample_id,
            'parameter_code' => $e->parameter_code,
            'standard_code' => $e->standard_code,
            'requirement_code' => $e->requirement_code,
            'requirement' => $e->requirement,
            'result' => $e->result,
            'verdict' => $e->verdict,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
