<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Support\Time;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

// M06 字典四实体 services 的共享小件：keyword 过滤 / 必填键 / 应用层时间戳
// （rails InspectionDictionary::Common 同构移植）。
trait Common
{
    /**
     * @return array{created_at: string, updated_at: string}
     */
    private function timestamps(): array
    {
        $now = Time::nowIso();

        return ['created_at' => $now, 'updated_at' => $now];
    }

    // 镜像 require! 的建路径语义：键缺失（null）-> IAE 400，按入参序报首个缺键
    /**
     * @param  array<string, mixed>  $body
     */
    private function requireFields(array $body, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (($body[$key] ?? null) === null) {
                throw new InvalidArgumentException("{$key} is required");
            }
        }
    }

    // 镜像 present?：字符串 trim 后空 = 缺席；其余 null = 缺席
    private function presentValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null;
    }

    // 镜像 mapper 列表查询：keyword LOWER(code) LIKE OR LOWER(name) LIKE（blank = 不过滤）
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function whereKeyword(Builder $query, mixed $keyword): Builder
    {
        if (! $this->presentValue($keyword)) {
            return $query;
        }
        $like = '%'.mb_strtolower((string) $keyword).'%';

        // rails LOWER(code) LIKE :kw OR LOWER(name) LIKE :kw 镜像（OR 组括死在闭包里）
        return $query->where(static function ($q) use ($like): void {
            $q->whereRaw('LOWER(code) LIKE ?', [$like])
                ->orWhereRaw('LOWER(name) LIKE ?', [$like]);
        });
    }
}
