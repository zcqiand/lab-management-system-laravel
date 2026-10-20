<?php

declare(strict_types=1);

namespace App\Services\ReportNames;

use App\Models\InspectionReportName;
use App\Services\InspectionDictionary\Common;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M06.F07 报告名称 —— lab-springboot InspectionReportNameService +
// InspectionReportNameMapper 1:1 镜像（rails ReportNames::InspectionReportNameService
// 同构移植）。平台级字典（无 tenant_id）。ext_fields 走 jsonb；DTO 端 null/blank
// 回退 []。create 同 code = merge 覆盖整行含 created_at（springboot repo.save =
// JPA merge 语义，共库四方对拍 2026-09-29 live run2/3 实锤）。
final class InspectionReportNameService
{
    use Common;
    use UpdateFields;

    // 镜像 InspectionReportNameMapper.applyUpdate 的字段判定
    private const UPDATE_FIELDS = [
        'name' => ['name', 'truthy'],
        'fullName' => ['full_name', 'truthy'],
        'templatePath' => ['template_path', 'truthy'],
        'summaryName' => ['summary_name', 'truthy'],
        'extFields' => ['ext_fields', 'truthy'],
        'description' => ['description', 'truthy'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword): array
    {
        return $this->whereKeyword(InspectionReportName::query(), $keyword)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (InspectionReportName $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $code): array
    {
        return $this->dto($this->found($code));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        if (($body['code'] ?? null) === null || ($body['name'] ?? null) === null) {
            throw new InvalidArgumentException('code and name are required');
        }

        // springboot repo.save = JPA merge（同 PK 覆盖整行，含 created_at）——
        // 共库四方对拍下先建方已占 code，裸 insert 会 UniqueViolation 500
        // （2026-09-29 live run2/3 实锤）。
        $entry = InspectionReportName::query()->where('code', $body['code'])->first()
            ?? new InspectionReportName;
        $entry->fill([
            'code' => $body['code'],
            'name' => $body['name'],
            'full_name' => $body['fullName'] ?? null,
            'template_path' => $body['templatePath'] ?? null,
            'summary_name' => $body['summaryName'] ?? null,
            'ext_fields' => $body['extFields'] ?? null,
            'description' => $body['description'] ?? null,
            // rails body['sortOrder'] || 0 镜像：缺省 0
            'sort_order' => $body['sortOrder'] ?? 0,
        ] + $this->timestamps());
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

    private function found(string $code): InspectionReportName
    {
        $entry = InspectionReportName::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionReportName::class, [$code]);
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionReportName $e): array
    {
        return [
            'code' => $e->code,
            'name' => $e->name,
            'full_name' => $e->full_name,
            'template_path' => $e->template_path,
            'summary_name' => $e->summary_name,
            // 镜像 deserialize：null/blank -> []（响应恒数组）
            'ext_fields' => is_array($e->ext_fields) ? $e->ext_fields : [],
            'description' => $e->description,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
