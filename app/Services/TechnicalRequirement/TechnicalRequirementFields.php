<?php

declare(strict_types=1);

namespace App\Services\TechnicalRequirement;

// TechnicalRequirementService 的字段装配半体（fromCreate 缺省 + DTO 拼装），
// 拆出以守住类体规模。镜像 rails TechnicalRequirement::TechnicalRequirementFields。
trait TechnicalRequirementFields
{
    // fromCreate 的枚举缺省：valueType=numeric / comparison=≥ /
    // judgmentMode=manual / verificationStatus=draft
    private const ENUM_DEFAULTS = [
        'valueType' => ['value_type', 'numeric'],
        'comparison' => ['comparison', '≥'],
        'judgmentMode' => ['judgment_mode', 'manual'],
        'verificationStatus' => ['verification_status', 'draft'],
    ];

    // 无缺省、直透 body 的可空列（key = 契约 camelCase 入参，value = DB snake_case 列，
    // rails json_key.underscore 镜像——house 口径显式映射，禁正则发明）
    private const NULLABLE = [
        'conditions' => 'conditions',
        'minValue' => 'min_value',
        'maxValue' => 'max_value',
        'targetValue' => 'target_value',
        'expression' => 'expression',
        'unit' => 'unit',
        'clause' => 'clause',
        'sourcePage' => 'source_page',
        'sourceHash' => 'source_hash',
        'brand' => 'brand',
        'model' => 'model',
        'grade' => 'grade',
        'spec' => 'spec',
        'sieve' => 'sieve',
        'remark' => 'remark',
    ];

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function createAttrs(array $body, string $tenantId, string $now): array
    {
        $attrs = [
            'tenant_id' => $tenantId,
            'inspection_object_code' => $body['inspectionObjectCode'],
            'inspection_parameter_code' => $body['inspectionParameterCode'],
            'judgment_standard_code' => $body['judgmentStandardCode'],
        ];
        foreach (self::ENUM_DEFAULTS as $jsonKey => [$column, $default]) {
            // rails body[json_key] || default 镜像：缺省走枚举缺省值
            $attrs[$column] = $body[$jsonKey] ?? $default;
        }
        foreach (self::NULLABLE as $jsonKey => $column) {
            $attrs[$column] = $body[$jsonKey] ?? null;
        }
        $attrs['sort_order'] = $body['sortOrder'] ?? 0;
        $attrs['created_at'] = $now;
        $attrs['updated_at'] = $now;

        return $attrs;
    }
}
