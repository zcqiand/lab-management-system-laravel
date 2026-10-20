<?php

declare(strict_types=1);

namespace App\Services\Auth;

// saas EffectiveMenuNode → lab 契约 MenuNode 递归映射（lab-springboot SaasMenuMapper
// 镜像，rails app/services/auth/saas_menu_mapper.rb 逐语义移植）。
// saas label 字段是 title（2026-09-14 实测），空回退 code；icon 空按 type 兜底
// （group→resource / 其余→file）；子树按 sortOrder 升序（nulls last，防御性再排）；
// path/children present 才带（空 children 数组不出键）。
final class SaasMenuMapper
{
    private const DEFAULT_GROUP_ICON = 'resource';

    private const DEFAULT_PAGE_ICON = 'file';

    /**
     * @param  list<array<string, mixed>>  $roots
     * @return list<array<string, mixed>>
     */
    public function map(array $roots): array
    {
        if ($roots === []) {
            return [];
        }

        $mapped = [];
        foreach ($this->sortByOrder($roots) as $node) {
            $mapped[] = $this->mapNode($node);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $src
     * @return array<string, mixed>
     */
    private function mapNode(array $src): array
    {
        $title = $src['title'] ?? null;
        $icon = $src['icon'] ?? null;
        $type = $src['type'] ?? null;
        $node = [
            'id' => $src['id'] ?? null,
            'label' => self::presence($title) ?? (string) ($src['code'] ?? ''),
            'icon' => self::presence($icon) ?? $this->defaultIcon(is_string($type) ? $type : ''),
        ];

        $path = self::presence($src['path'] ?? null);
        if ($path !== null) {
            $node['path'] = $path;
        }

        $children = $src['children'] ?? null;
        if (is_array($children) && $children !== []) {
            $node['children'] = $this->map($children);
        }

        return $node;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function sortByOrder(array $nodes): array
    {
        usort($nodes, fn (array $a, array $b): int => self::orderOf($a) <=> self::orderOf($b));

        return $nodes;
    }

    /** @param array<string, mixed> $node */
    private static function orderOf(array $node): float
    {
        $order = $node['sortOrder'] ?? null;

        return is_int($order) || is_float($order) ? (float) $order : INF;
    }

    private function defaultIcon(string $type): string
    {
        return $type === 'group' ? self::DEFAULT_GROUP_ICON : self::DEFAULT_PAGE_ICON;
    }

    /** rails String#presence 镜像：非空串原样，其余 null。 */
    private static function presence(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
