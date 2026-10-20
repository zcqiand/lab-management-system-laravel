<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

// 字典域 services 共用的 applyUpdate 字段级镜像（rails app/services/update_fields.rb
// 同构移植）。mapping 形如 ['jsonKey' => ['column', mode]]，mode 对应 springboot
// mapper 的三种判定：
// - 'truthy' —— 值 truthy 才写（字符串字段，镜像 `if (hasText(...))` / `if body[x]`；
//   Ruby 真值口径：仅 nil/false 不写——空串/0 是合法值照写，禁用 PHP falsy（"0" 会误伤））
// - 'strict' —— 非 null 才写（布尔/数值字段，镜像 `if (x != null)`）
// - 'key'    —— body 带键就写（允许显式置 null，镜像 jsonb 可清空字段）
// updated_at 恒刷新由调用方负责。
trait UpdateFields
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, array{0: string, 1: string}>  $mapping
     */
    private function applyFields(Model $entity, array $body, array $mapping): void
    {
        foreach ($mapping as $jsonKey => [$column, $mode]) {
            if ($this->writable($body, $jsonKey, $mode)) {
                $entity->{$column} = $body[$jsonKey];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function writable(array $body, string $jsonKey, string $mode): bool
    {
        $value = $body[$jsonKey] ?? null;
        if ($mode === 'truthy') {
            // rails body[json_key] 真值判定镜像：仅 nil/false 拒写
            return $value !== null && $value !== false;
        }
        if ($mode === 'strict') {
            return $value !== null;
        }

        return array_key_exists($jsonKey, $body);
    }
}
