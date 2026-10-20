<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionStandard;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// M06.F04 检测标准 —— InspectionStandard（全局字典，PK code）。
// status 缺省 active。rails InspectionDictionary::Standards 同构移植；code 寻址
// miss → ModelNotFoundException（controller catch → 404 逐字消息）。
final class Standards
{
    use Common;
    use UpdateFields;

    private const UPDATE_FIELDS = [
        'name' => ['name', 'truthy'],
        'version' => ['version', 'key'],
        'status' => ['status', 'truthy'],
        'sourceDocumentId' => ['source_document_id', 'key'],
        'sourceHash' => ['source_hash', 'key'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword, ?string $status): array
    {
        $query = $this->whereKeyword(InspectionStandard::query(), $keyword);
        if ($this->presentValue($status)) {
            $query = $query->where('status', $status);
        }

        return $query->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (InspectionStandard $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $this->requireFields($body, 'code', 'name');

        $entry = new InspectionStandard;
        $entry->fill($this->attrs($body) + $this->timestamps());
        $entry->save();

        return $this->dto($entry);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $code, array $body): array
    {
        $entry = $this->found($code);
        $this->applyFields($entry, $body, self::UPDATE_FIELDS);
        $entry->updated_at = Time::nowIso();
        $entry->save();

        return $this->dto($entry);
    }

    public function delete(string $code): void
    {
        $this->found($code)->delete();
    }

    private function found(string $code): InspectionStandard
    {
        $entry = InspectionStandard::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionStandard::class, [$code]);
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function attrs(array $body): array
    {
        return [
            'code' => $body['code'],
            'name' => $body['name'],
            'version' => $body['version'] ?? null,
            // rails body['status'] || 'active' 镜像：缺省 active
            'status' => $this->defaultFor($body, 'status', 'active'),
            'source_document_id' => $body['sourceDocumentId'] ?? null,
            'source_hash' => $body['sourceHash'] ?? null,
            'sort_order' => $body['sortOrder'] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionStandard $e): array
    {
        return [
            'code' => $e->code,
            'name' => $e->name,
            'version' => $e->version,
            'status' => $e->status,
            'source_document_id' => $e->source_document_id,
            'source_hash' => $e->source_hash,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
