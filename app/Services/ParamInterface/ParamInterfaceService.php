<?php

declare(strict_types=1);

namespace App\Services\ParamInterface;

use App\Models\InspectionParamInterface;
use App\Services\InspectionDictionary\Common;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M10 参数接口 —— lab-springboot ParamInterfaceService + ParamInterfaceMapper
// 1:1 镜像（rails ParamInterface::ParamInterfaceService 同构移植）。平台级字典
// （无 tenant_id）。config 走 jsonb；DTO 端 null/blank 回退 {}。create 必填
// code + componentPath（ct 实证：宽松 mock 曾掩盖）。
final class ParamInterfaceService
{
    use Common;
    use UpdateFields;

    // 镜像 ParamInterfaceMapper.applyUpdate：truthy 字段跳过 nil/false，updated_at 恒刷新
    private const UPDATE_FIELDS = [
        'name' => ['name', 'truthy'],
        'componentPath' => ['component_path', 'truthy'],
        'description' => ['description', 'truthy'],
        'isOfficial' => ['is_official', 'strict'],
        'sortOrder' => ['sort_order', 'truthy'],
        'config' => ['config', 'truthy'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $keyword): array
    {
        return $this->whereKeyword(InspectionParamInterface::query(), $keyword)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (InspectionParamInterface $e): array => $this->dto($e))
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
        if (($body['code'] ?? null) === null || ($body['componentPath'] ?? null) === null) {
            throw new InvalidArgumentException('code and componentPath are required');
        }

        $entry = new InspectionParamInterface;
        $entry->fill([
            'code' => $body['code'],
            'name' => $body['name'] ?? null,
            'component_path' => $body['componentPath'],
            'description' => $body['description'] ?? null,
            'is_official' => $body['isOfficial'] ?? null,
            // rails body['sortOrder'] || 0 镜像：缺省 0
            'sort_order' => $body['sortOrder'] ?? 0,
            'config' => $body['config'] ?? null,
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

    private function found(string $code): InspectionParamInterface
    {
        $entry = InspectionParamInterface::query()->where('code', $code)->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)->setModel(InspectionParamInterface::class, [$code]);
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionParamInterface $e): array
    {
        return [
            'code' => $e->code,
            'name' => $e->name,
            'component_path' => $e->component_path,
            'description' => $e->description,
            'is_official' => $e->is_official,
            'sort_order' => $e->sort_order,
            // 镜像 deserialize：null/blank -> {}（响应恒对象）
            'config' => is_array($e->config) ? $e->config : [],
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
