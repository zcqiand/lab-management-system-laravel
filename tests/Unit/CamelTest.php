<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Camel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Camel::deep 契约（Task 5-13 渲染消费面）：snake_case → lowerCamel 深变换，逐语义
// 移植 saas 同名件（rails ApiSupport::JsonCamelizeKey 镜像，render_camel 底座）——
// 嵌套递归、null 值键剔除（@Nullable normalize 语义：字段缺失即不出现在 JSON 里）、
// list 保 null 保序。
final class CamelTest extends TestCase
{
    #[Test]
    public function snake_keys_become_lower_camel(): void
    {
        $this->assertSame(
            ['userId' => 1, 'createdAt' => '2026-10-20', 'inspectionObjectCode' => 'IO-001'],
            Camel::deep(['user_id' => 1, 'created_at' => '2026-10-20', 'inspection_object_code' => 'IO-001'])
        );
    }

    #[Test]
    public function nested_maps_and_lists_are_recursed(): void
    {
        $this->assertSame(
            [
                'inspectionObject' => [
                    'parameterCodes' => ['p1', 'p2'],
                    'specialtyView' => ['code' => 'SP-1'],
                ],
                'items' => [
                    ['contractNo' => 'c1'],
                    ['contractNo' => 'c2'],
                ],
            ],
            Camel::deep([
                'inspection_object' => [
                    'parameter_codes' => ['p1', 'p2'],
                    'specialty_view' => ['code' => 'SP-1'],
                ],
                'items' => [
                    ['contract_no' => 'c1'],
                    ['contract_no' => 'c2'],
                ],
            ])
        );
    }

    #[Test]
    public function null_values_are_dropped_but_list_nulls_are_kept(): void
    {
        // 家族契约：可选字段缺失即不出现在 JSON 里，不是 "field": null；
        // null 剔除只作用于 map 键值，不波及列表元素（list 保 null 保序）。
        $this->assertSame(
            ['b' => 2, 'c' => ['d' => 1], 'list' => [null, 'x']],
            Camel::deep(['a' => null, 'b' => 2, 'c' => ['d' => 1, 'e' => null], 'list' => [null, 'x']])
        );
    }

    #[Test]
    public function plain_camel_and_numeric_keys_are_unchanged(): void
    {
        $this->assertSame(
            ['tenantId' => 'TENANT-001', '1' => 'x', 'code' => 'k'],
            Camel::deep(['tenantId' => 'TENANT-001', '1' => 'x', 'code' => 'k'])
        );
    }

    #[Test]
    public function gap_keyed_map_is_treated_as_hash_and_keeps_its_keys(): void
    {
        // 非连续数字键不是 list（array_is_list=false）——走 Hash 分支：键原样保留、
        // null 值键剔除。json_encode 退化成对象是 rails Hash 行为的忠实镜像
        // （ruby 端 {0=>.., 2=>..} 序列化同样是 JSON object），不是本件缺陷。
        $this->assertSame(
            [0 => ['id' => 1], 2 => ['id' => 3]],
            Camel::deep([0 => ['id' => 1, 'tmp' => null], 2 => ['id' => 3, 'tmp' => null]])
        );
        $this->assertJsonStringEqualsJsonString(
            '{"0":{"id":1},"2":{"id":3}}',
            (string) json_encode(Camel::deep([0 => ['id' => 1, 'tmp' => null], 2 => ['id' => 3, 'tmp' => null]]))
        );
    }

    #[Test]
    public function true_list_survives_deep_as_a_json_array(): void
    {
        // 连续 list 分支镜像：剔键后仍是 list，json_encode 不退化成对象——这是
        // 家族 items 面的主路径。
        $this->assertSame(
            [['id' => 1], ['id' => 3]],
            Camel::deep([['id' => 1, 'tmp' => null], ['id' => 3, 'tmp' => null]])
        );
        $this->assertJsonStringEqualsJsonString(
            '[{"id":1},{"id":3}]',
            (string) json_encode(Camel::deep([['id' => 1, 'tmp' => null], ['id' => 3, 'tmp' => null]]))
        );
    }
}
