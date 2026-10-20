<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionObject;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// M06.F02 检测对象 —— InspectionObject（全局字典，PK code）。
// 必填 code/specialty/sourceProjectNo/sourceProjectName/name；
// isOptionalForQualification 缺省 false。rails InspectionDictionary::Objects 同构移植；
// code 寻址 miss → ModelNotFoundException（controller catch → 404 逐字消息）。
final class Objects
{
    use Common;
    use UpdateFields;

    private const UPDATE_FIELDS = [
        'inspectionSpecialtyCode' => ['inspection_specialty_code', 'truthy'],
        'sourceProjectNo' => ['source_project_no', 'truthy'],
        'sourceProjectName' => ['source_project_name', 'truthy'],
        'name' => ['name', 'truthy'],
        'isOptionalForQualification' => ['is_optional_for_qualification', 'strict'],
        'isOfficial' => ['is_official', 'strict'],
        'enabled' => ['enabled', 'strict'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword, ?string $specialtyCode): array
    {
        $query = $this->whereKeyword(InspectionObject::query(), $keyword);
        if ($this->presentValue($specialtyCode)) {
            $query = $query->where('inspection_specialty_code', $specialtyCode);
        }

        return $query->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (InspectionObject $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $this->requireFields($body, 'code', 'inspectionSpecialtyCode', 'sourceProjectNo',
            'sourceProjectName', 'name');

        $entry = new InspectionObject;
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

    private function found(string $code): InspectionObject
    {
        $entry = InspectionObject::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionObject::class, [$code]);
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
            'inspection_specialty_code' => $body['inspectionSpecialtyCode'],
            'source_project_no' => $body['sourceProjectNo'],
            'source_project_name' => $body['sourceProjectName'],
            'name' => $body['name'],
            // rails body.fetch('isOptionalForQualification', false) 镜像：缺省 false
            'is_optional_for_qualification' => $body['isOptionalForQualification'] ?? false,
            'is_official' => $body['isOfficial'] ?? true,
            'enabled' => $body['enabled'] ?? true,
            'sort_order' => $this->defaultFor($body, 'sortOrder', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionObject $e): array
    {
        return [
            'code' => $e->code,
            'inspection_specialty_code' => $e->inspection_specialty_code,
            'source_project_no' => $e->source_project_no,
            'source_project_name' => $e->source_project_name,
            'name' => $e->name,
            'is_optional_for_qualification' => $e->is_optional_for_qualification,
            'is_official' => $e->is_official,
            'enabled' => $e->enabled,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
