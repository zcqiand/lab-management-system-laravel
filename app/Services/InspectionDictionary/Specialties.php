<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionSpecialty;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// M06.F01 检测专项 —— InspectionSpecialty（全局字典，无租户列，PK code）。
// rails InspectionDictionary::Specialties 同构移植。officialNo NOT NULL（ct T11 实证）。
// code 寻址 miss → ModelNotFoundException，消费方（controller）catch 后 404
// NOT_FOUND 'InspectionSpecialty not found: <code>'（rails 逐字，业务串码不落
// 基座 uuid 分叉）。
final class Specialties
{
    use Common;
    use UpdateFields;

    // 镜像 mapper.applyUpdate 的字段判定：['jsonKey' => ['column', mode]]
    private const UPDATE_FIELDS = [
        'officialNo' => ['official_no', 'truthy'],
        'name' => ['name', 'truthy'],
        'isOfficial' => ['is_official', 'strict'],
        'enabled' => ['enabled', 'strict'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword): array
    {
        $query = $this->whereKeyword(InspectionSpecialty::query(), $keyword)
            ->orderBy('sort_order')
            ->orderBy('code');

        return $query->get()
            ->map(fn (InspectionSpecialty $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $this->requireFields($body, 'code', 'officialNo', 'name');

        $entry = new InspectionSpecialty;
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
        // 镜像 applyUpdate：三 mode 字段级判定，updated_at 恒刷新
        $this->applyFields($entry, $body, self::UPDATE_FIELDS);
        $entry->updated_at = Time::nowIso();
        $entry->save();

        return $this->dto($entry);
    }

    public function delete(string $code): void
    {
        $this->found($code)->delete();
    }

    private function found(string $code): InspectionSpecialty
    {
        $entry = InspectionSpecialty::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionSpecialty::class, [$code]);
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
            'official_no' => $body['officialNo'],
            'name' => $body['name'],
            // rails body.fetch('isOfficial', true) 镜像：缺省 true
            'is_official' => $body['isOfficial'] ?? true,
            'enabled' => $body['enabled'] ?? true,
            // rails body['sortOrder'] || 0 镜像：缺省 0
            'sort_order' => $this->defaultFor($body, 'sortOrder', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionSpecialty $e): array
    {
        return [
            'code' => $e->code,
            'official_no' => $e->official_no,
            'name' => $e->name,
            'is_official' => $e->is_official,
            'enabled' => $e->enabled,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
