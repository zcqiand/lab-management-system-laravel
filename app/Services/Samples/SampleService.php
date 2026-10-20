<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Sample;
use App\Models\SampleReceipt;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

// M03.F02/F03 样品域（lab-springboot SampleService/SampleMapper 镜像 → rails
// Samples::SampleService 的 Laravel 同构移植）。
// tenant-scoped + receipt FK 校验（NSEE→404，不是 FK→400 —— service 层显式查，
// controller catch ModelNotFoundException 渲染「Receipt not found: <id>」逐字）；
// keyword 过滤 sample_code/sample_name（LOWER LIKE）；排序 created_at DESC, sample_code。
final class SampleService
{
    // rails UPDATE_FIELDS camel→snake 逐字段映射（field_attrs + f.underscore 同义）
    private const UPDATE_FIELDS = [
        'sampleName' => 'sample_name',
        'model' => 'model',
        'specification' => 'specification',
        'grade' => 'grade',
        'brand' => 'brand',
        'manufacturer' => 'manufacturer',
        'structuralPart' => 'structural_part',
        'representQuantity' => 'represent_quantity',
        'sampleQuantity' => 'sample_quantity',
        'batchNumber' => 'batch_number',
        'supplyUnit' => 'supply_unit',
        'arrivalDate' => 'arrival_date',
        'samplingDate' => 'sampling_date',
        'curingCondition' => 'curing_condition',
        'age' => 'age',
        'ext' => 'ext',
        'remark' => 'remark',
    ];

    // rails %w[receiptId sampleCode] + UPDATE_FIELDS（列表拼接，键不重叠）
    private const CREATE_FIELDS = [
        'receiptId' => 'receipt_id',
        'sampleCode' => 'sample_code',
    ] + self::UPDATE_FIELDS;

    public function __construct(private readonly string $tenantId) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $receiptId, ?string $keyword): array
    {
        $query = Sample::query()->where('tenant_id', $this->tenantId);
        if ($receiptId !== null && $receiptId !== '') {
            $query->where('receipt_id', $receiptId);
        }
        // rails filter_keyword 镜像：LOWER(sample_code) LIKE :k OR LOWER(sample_name) LIKE :k
        $keyword = (string) $keyword;
        if ($keyword !== '') {
            $like = '%'.mb_strtolower($keyword).'%';
            $query->whereRaw('(LOWER(sample_code) LIKE ? OR LOWER(sample_name) LIKE ?)', [$like, $like]);
        }

        return $query->orderByDesc('created_at')
            ->orderBy('sample_code')
            ->get()
            ->map(fn (Sample $e): array => $this->dto($e))
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
        // receipt FK 显式校验（NSEE→404，rails SampleReceipt.exists? 语义；消息逐字
        // 「Receipt not found: <id>」，controller catch ModelNotFoundException 渲染）
        $receiptId = $body['receiptId'] ?? null;
        if (! is_string($receiptId)) {
            $receiptId = null;
        }
        $exists = SampleReceipt::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $receiptId)
            ->exists();
        if (! $exists) {
            throw (new ModelNotFoundException)->setModel(SampleReceipt::class, [(string) $receiptId]);
        }

        $now = $this->nowIso();
        $attrs = $this->fieldAttrs($body, self::CREATE_FIELDS);
        // rails req['ext'] || {} 镜像：仅 nil/false 回退 []（空 {} 合法照写）
        $ext = $body['ext'] ?? null;
        $attrs['ext'] = ($ext === null || $ext === false) ? [] : $ext;
        $attrs['id'] = $this->newId();
        $attrs['tenant_id'] = $this->tenantId;
        $attrs['created_at'] = $now;
        $attrs['updated_at'] = $now;

        $sample = new Sample($attrs);
        // NOT NULL（sample_code）/ FK 违反抛 QueryException → 基座映射 400
        $sample->save();

        return $this->dto($sample);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        $sample = $this->find($id);
        // 镜像 field_attrs(...).compact.each + ext unless nil：null 字段跳过（部分更新），
        // ext 同 strict（nil 不写，整体替换走 updateExt）
        foreach (self::UPDATE_FIELDS as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $sample->{$column} = $value;
            }
        }
        $sample->updated_at = $this->nowIso();
        $sample->save();

        return $this->dto($sample);
    }

    // M03.F01.I07 ext 补录：整体替换（合并是前端职责）；契约 ext 必填（5.89，缺省 IAE→400，
    // 校验先于查无——springboot updateExt 镜像）
    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateExt(string $id, array $body): array
    {
        if (! array_key_exists('ext', $body) || $body['ext'] === null) {
            throw new \InvalidArgumentException('ext is required');
        }

        $sample = $this->find($id);
        $sample->ext = $body['ext'];
        $sample->updated_at = $this->nowIso();
        $sample->save();

        return $this->dto($sample);
    }

    public function delete(string $id): void
    {
        $this->find($id)->delete();
    }

    private function find(string $id): Sample
    {
        $sample = Sample::query()
            ->where('tenant_id', $this->tenantId)
            ->where('id', $id)
            ->first();
        if ($sample === null) {
            throw (new ModelNotFoundException)->setModel(Sample::class, [$id]);
        }

        return $sample;
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
        return 'S-'.Str::uuid()->toString();
    }

    private function nowIso(): string
    {
        return Time::nowIso();
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(Sample $e): array
    {
        $ext = $e->ext;

        return [
            'id' => $e->id,
            'receipt_id' => $e->receipt_id,
            'sample_code' => $e->sample_code,
            'sample_name' => $e->sample_name,
            'model' => $e->model,
            'specification' => $e->specification,
            'grade' => $e->grade,
            'brand' => $e->brand,
            'manufacturer' => $e->manufacturer,
            'structural_part' => $e->structural_part,
            'represent_quantity' => $e->represent_quantity,
            'sample_quantity' => $e->sample_quantity,
            'batch_number' => $e->batch_number,
            'supply_unit' => $e->supply_unit,
            'arrival_date' => $e->arrival_date,
            'sampling_date' => $e->sampling_date,
            'curing_condition' => $e->curing_condition,
            'age' => $e->age,
            // rails e.ext || {} 镜像：仅 nil/false 回退 []（空 {} 合法照写）
            'ext' => is_array($ext) ? $ext : [],
            'remark' => $e->remark,
            'tenant_id' => $e->tenant_id,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
