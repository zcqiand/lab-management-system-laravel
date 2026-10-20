<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\InspectionBrand;
use App\Models\InspectionGrade;
use App\Models\InspectionModel;
use App\Models\InspectionSpec;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M04.F06/07/08/09 码表服务 —— lab-springboot CatalogService +
// InspectionCatalogMapper 1:1 镜像（rails Catalog::CatalogService 的 Laravel 同构移植）。
// 4 表结构一致（brand/model/spec/grade）：tenant 隔离（unique (tenant_id, code)，
// code 是全局 PK 列但按 tenant 过滤后寻址），list 按 inspectionObjectCode + keyword
// （code/name 大小写不敏感 LIKE）过滤，排序 sort_order, code；DTO 蛇形 hash，
// camelCase 由渲染层处理。ModelNotFoundException（code 寻址 miss）由基座映射
// 404 NOT_FOUND——{code} 非路由 uuid 形参，不落 400 malformed 分叉。
// 四表 @property 集合一致（见各 model 薄包装），union 上属性访问安全。
// @phpstan-type CatalogEntry InspectionBrand|InspectionModel|InspectionSpec|InspectionGrade
final class CatalogService
{
    use UpdateFields;

    /** @var array<string, array{model: class-string<InspectionBrand>|class-string<InspectionModel>|class-string<InspectionSpec>|class-string<InspectionGrade>, label: string}> */
    private const KINDS = [
        'brand' => ['model' => InspectionBrand::class, 'label' => 'Brand'],
        'model' => ['model' => InspectionModel::class, 'label' => 'Model'],
        'spec' => ['model' => InspectionSpec::class, 'label' => 'Spec'],
        'grade' => ['model' => InspectionGrade::class, 'label' => 'Grade'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $kind, string $tenantId, ?string $objectCode, ?string $keyword): array
    {
        $query = $this->modelClass($kind)::query()->where('tenant_id', $tenantId);
        // present? 语义：null / 空串均视为缺席（rails object_code.present? 镜像）
        if (is_string($objectCode) && $objectCode !== '') {
            $query->where('inspection_object_code', $objectCode);
        }
        if (is_string($keyword) && $keyword !== '') {
            $like = '%'.mb_strtolower($keyword).'%';
            $query->where(static function ($q) use ($like): void {
                // rails LOWER(code) LIKE :kw OR LOWER(name) LIKE :kw 镜像
                $q->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
            });
        }
        $query->orderBy('sort_order')->orderBy('code');

        return $query->get()
            ->map(fn (InspectionBrand|InspectionModel|InspectionSpec|InspectionGrade $e): array => $this->entryDto($e))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(string $kind, string $tenantId, array $body): array
    {
        if (($body['code'] ?? null) === null || ($body['name'] ?? null) === null) {
            throw new InvalidArgumentException('code and name are required');
        }

        $now = Time::nowIso();
        $entry = $this->modelClass($kind)::query()->create($this->entryAttrs($tenantId, $body, $now));

        return $this->entryDto($entry);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $kind, string $tenantId, string $code, array $body): array
    {
        $entry = $this->find($kind, $tenantId, $code);
        // 镜像 applyUpdate：null 字段跳过（部分更新），updated_at 恒刷新
        foreach (['inspectionObjectCode' => 'inspection_object_code', 'name' => 'name', 'remark' => 'remark'] as $camel => $column) {
            $value = $body[$camel] ?? null;
            if ($value !== null) {
                $entry->{$column} = $value;
            }
        }
        $sortOrder = $body['sortOrder'] ?? null;
        if ($sortOrder !== null) {
            $entry->sort_order = (int) $sortOrder;
        }
        $entry->updated_at = Time::nowIso();
        $entry->save();

        return $this->entryDto($entry);
    }

    public function delete(string $kind, string $tenantId, string $code): void
    {
        $this->find($kind, $tenantId, $code)->delete();
    }

    /**
     * @return class-string<InspectionBrand>|class-string<InspectionModel>|class-string<InspectionSpec>|class-string<InspectionGrade>
     */
    private function modelClass(string $kind): string
    {
        $meta = self::KINDS[$kind] ?? null;
        if ($meta === null) {
            throw new InvalidArgumentException("Unexpected value '{$kind}'");
        }

        return $meta['model'];
    }

    // @phpstan-return CatalogEntry
    private function find(string $kind, string $tenantId, string $code): InspectionBrand|InspectionModel|InspectionSpec|InspectionGrade
    {
        $entry = $this->modelClass($kind)::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();
        if ($entry === null) {
            // rails `"<label> not found: <code>"` 逐字（基座 404 映射取 exception message）
            throw new ModelNotFoundException(self::KINDS[$kind]['label']." not found: {$code}");
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function entryAttrs(string $tenantId, array $body, string $now): array
    {
        return [
            'tenant_id' => $tenantId,
            'code' => $body['code'],
            'inspection_object_code' => $body['inspectionObjectCode'] ?? null,
            'name' => $body['name'],
            'remark' => $body['remark'] ?? null,
            // rails body['sortOrder'] || 0 镜像（Ruby 真值 coalesce）
            'sort_order' => $this->defaultFor($body, 'sortOrder', 0),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    // @phpstan-param CatalogEntry $e
    private function entryDto(InspectionBrand|InspectionModel|InspectionSpec|InspectionGrade $e): array
    {
        return [
            'code' => $e->code,
            'tenant_id' => $e->tenant_id,
            'inspection_object_code' => $e->inspection_object_code,
            'name' => $e->name,
            'remark' => $e->remark,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
