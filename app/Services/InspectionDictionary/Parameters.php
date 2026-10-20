<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionParameter;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// M06.F03 检测参数 —— InspectionParameter（全局字典，PK code）。
// rawName/canonicalName 缺省=name；aliases jsonb 缺省 []；sourceType 缺省 official。
// rails InspectionDictionary::Parameters 同构移植；code 寻址 miss →
// ModelNotFoundException（controller catch → 404 逐字消息）。
final class Parameters
{
    use Common;
    use UpdateFields;

    private const UPDATE_FIELDS = [
        'name' => ['name', 'truthy'],
        'rawName' => ['raw_name', 'truthy'],
        'canonicalName' => ['canonical_name', 'truthy'],
        'methodText' => ['method_text', 'key'],
        'aliases' => ['aliases', 'key'],
        'unit' => ['unit', 'key'],
        'sourceType' => ['source_type', 'truthy'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword, ?string $sourceType): array
    {
        $query = $this->whereKeyword(InspectionParameter::query(), $keyword);
        if ($this->presentValue($sourceType)) {
            $query = $query->where('source_type', $sourceType);
        }

        return $query->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (InspectionParameter $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $this->requireFields($body, 'code', 'name');

        $entry = new InspectionParameter;
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

    private function found(string $code): InspectionParameter
    {
        $entry = InspectionParameter::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionParameter::class, [$code]);
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
            // rails body['rawName'] || body['name'] 镜像：缺省回退 name
            'raw_name' => $body['rawName'] ?? $body['name'],
            'canonical_name' => $body['canonicalName'] ?? $body['name'],
            'method_text' => $body['methodText'] ?? null,
            // rails body['aliases'] || [] 镜像：缺省空数组
            'aliases' => $body['aliases'] ?? [],
            'unit' => $body['unit'] ?? null,
            'source_type' => $body['sourceType'] ?? 'official',
            'sort_order' => $body['sortOrder'] ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionParameter $e): array
    {
        return [
            'code' => $e->code,
            'name' => $e->name,
            'raw_name' => $e->raw_name,
            'canonical_name' => $e->canonical_name,
            'method_text' => $e->method_text,
            // 镜像 deserialize：aliases null/非数组 -> []（响应恒数组）
            'aliases' => is_array($e->aliases) ? $e->aliases : [],
            'unit' => $e->unit,
            'source_type' => $e->source_type,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
