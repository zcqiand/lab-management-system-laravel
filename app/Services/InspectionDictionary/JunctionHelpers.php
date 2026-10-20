<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Support\Time;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

// 8 个 junction service 共享的小件（link 校验 / upsert / list 过滤）——rails
// InspectionDictionary::JunctionHelpers 同构移植。本任务实装 4 组核心 junction；
// report-name 3 组 + param-interface 1 组属后续任务，复用本 trait。
trait JunctionHelpers
{
    // 镜像 InspectionJunctionService.requireNonBlank：null / 空串 → IAE 400，
    // 按入参序报首个违规（unlink 不走此校验）
    private function requireNonBlank(mixed ...$values): void
    {
        foreach ($values as $value) {
            if ($value === null) {
                throw new InvalidArgumentException('junction link fields must be non-null');
            }
            if (is_string($value) && trim($value) === '') {
                throw new InvalidArgumentException('junction link fields must be non-blank');
            }
        }
    }

    // upsert：同 PK 已存在则整行覆盖（含 created_at —— springboot save=merge 语义）。
    // junction 表是复合 PK，scaffold 口径模型 primaryKey=null（禁单键模型写路径），
    // 故走查询构造器 updateOrInsert：exists → UPDATE 全 values 列（created_at 恒刷，
    // 与 rails upsert! find_or_new + 全列 assign 同语义）；miss → INSERT keys+values。
    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $keyAttrs
     * @param  array<string, mixed>  $payload
     */
    private function upsert(string $model, array $keyAttrs, array $payload): void
    {
        $now = Time::nowIso();
        $model::query()->updateOrInsert(
            $keyAttrs,
            $payload + ['created_at' => $now, 'updated_at' => $now]
        );
    }

    // unlink 定位删除：命中才删，未命中静默 no-op（幂等 204）
    /**
     * @param  class-string  $model
     * @param  array<string, mixed>  $keyAttrs
     */
    private function deleteByKeys(string $model, array $keyAttrs): void
    {
        // 复合 PK 模型 primaryKey=null，模型级 delete() 的 key 定位不可用——
        // where 全键显式定位后走查询构造器删除（scaffold 注释同款口径）
        $model::query()->where($keyAttrs)->delete();
    }

    // list 过滤：attrs 首值 blank（null/空串）= 不过滤，否则 where 全 attrs
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $attrs
     * @return Builder<TModel>
     */
    private function filter(Builder $query, array $attrs): Builder
    {
        $value = reset($attrs);
        if (! $this->presentValue($value)) {
            return $query;
        }

        return $query->where($attrs);
    }

    private function presentValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }
}
