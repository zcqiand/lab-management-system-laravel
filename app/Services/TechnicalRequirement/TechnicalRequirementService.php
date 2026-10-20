<?php

declare(strict_types=1);

namespace App\Services\TechnicalRequirement;

use App\Models\InspectionTechnicalRequirement;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M06.F06 技术要求 —— lab-springboot TechnicalRequirementService +
// TechnicalRequirementMapper 1:1 镜像（rails TechnicalRequirement::
// TechnicalRequirementService 同构移植）。PK 是业务三键
// (object, parameter, judgmentStandard)；tenant-scoped（V012）。
// list 4 过滤（object/parameter/standard/status）+ tenant 收口，排序
// sort_order, object, parameter, standard。字段装配见 TechnicalRequirementFields。
final class TechnicalRequirementService
{
    use TechnicalRequirementFields;
    use UpdateFields;

    // 镜像 TechnicalRequirementMapper.applyUpdate：nil 字段跳过（strict），updated_at 恒刷新
    private const UPDATE_FIELDS = [
        'conditions' => ['conditions', 'strict'],
        'valueType' => ['value_type', 'strict'],
        'minValue' => ['min_value', 'strict'],
        'maxValue' => ['max_value', 'strict'],
        'targetValue' => ['target_value', 'strict'],
        'expression' => ['expression', 'strict'],
        'unit' => ['unit', 'strict'],
        'comparison' => ['comparison', 'strict'],
        'judgmentMode' => ['judgment_mode', 'strict'],
        'verificationStatus' => ['verification_status', 'strict'],
        'clause' => ['clause', 'strict'],
        'sourcePage' => ['source_page', 'strict'],
        'sourceHash' => ['source_hash', 'strict'],
        'brand' => ['brand', 'strict'],
        'model' => ['model', 'strict'],
        'grade' => ['grade', 'strict'],
        'spec' => ['spec', 'strict'],
        'sieve' => ['sieve', 'strict'],
        'remark' => ['remark', 'strict'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    public function __construct(private readonly string $tenantId) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        ?string $objectCode,
        ?string $parameterCode,
        ?string $standardCode,
        ?string $status
    ): array {
        $query = InspectionTechnicalRequirement::query()->where('tenant_id', $this->tenantId);
        if ($objectCode !== null && $objectCode !== '') {
            $query->where('inspection_object_code', $objectCode);
        }
        if ($parameterCode !== null && $parameterCode !== '') {
            $query->where('inspection_parameter_code', $parameterCode);
        }
        if ($standardCode !== null && $standardCode !== '') {
            $query->where('judgment_standard_code', $standardCode);
        }
        if ($status !== null && $status !== '') {
            $query->where('verification_status', $status);
        }

        return $query->orderBy('sort_order')
            ->orderBy('inspection_object_code')
            ->orderBy('inspection_parameter_code')
            ->orderBy('judgment_standard_code')
            ->get()
            ->map(fn (InspectionTechnicalRequirement $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $objectCode, string $parameterCode, string $standardCode): array
    {
        return $this->dto($this->found($objectCode, $parameterCode, $standardCode));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $this->requireKeys($body);
        $now = Time::nowIso();
        // springboot repo.save = JPA merge（同 PK 覆盖整行）——共库四方对拍下
        // 裸 insert 会 UniqueViolation 500（2026-09-29 live run2/3 实锤）。
        // 复合 PK 模型 scaffold 口径 primaryKey=null（禁单键模型写路径），走查询
        // 构造器 updateOrInsert：exists → UPDATE 全列，miss → INSERT（含 created_at
        // 恒刷，与 rails find_or_new + 全列 assign 同语义）。
        $keys = [
            'tenant_id' => $this->tenantId,
            'inspection_object_code' => $body['inspectionObjectCode'],
            'inspection_parameter_code' => $body['inspectionParameterCode'],
            'judgment_standard_code' => $body['judgmentStandardCode'],
        ];
        InspectionTechnicalRequirement::query()
            ->where('tenant_id', $this->tenantId)
            ->where('inspection_object_code', $keys['inspection_object_code'])
            ->where('inspection_parameter_code', $keys['inspection_parameter_code'])
            ->where('judgment_standard_code', $keys['judgment_standard_code'])
            ->updateOrInsert($keys, $this->createAttrs($body, $this->tenantId, $now));

        return $this->dto($this->found(
            $keys['inspection_object_code'],
            $keys['inspection_parameter_code'],
            $keys['judgment_standard_code']
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $objectCode, string $parameterCode, string $standardCode, array $body): array
    {
        $entry = $this->found($objectCode, $parameterCode, $standardCode);
        // 镜像 TechnicalRequirementMapper.applyUpdate：nil 字段跳过，updated_at 恒刷新。
        // 复合 PK 禁模型级 save()（primaryKey=null 无 where 定位键），字段装配在
        // 读出实例上算，落库走查询构造器 where 全键显式定位（scaffold 同款口径）。
        $this->applyFields($entry, $body, self::UPDATE_FIELDS);
        $entry->updated_at = Time::nowIso();
        InspectionTechnicalRequirement::query()
            ->where('tenant_id', $this->tenantId)
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->where('judgment_standard_code', $standardCode)
            ->update($entry->getAttributes());

        return $this->dto($entry);
    }

    public function delete(string $objectCode, string $parameterCode, string $standardCode): void
    {
        $this->found($objectCode, $parameterCode, $standardCode);
        InspectionTechnicalRequirement::query()
            ->where('tenant_id', $this->tenantId)
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->where('judgment_standard_code', $standardCode)
            ->delete();
    }

    // ==== private 段（rails 同位） ====

    // 三业务键任一缺失 -> IAE（镜像 service 的 requireNonNull 组）
    /**
     * @param  array<string, mixed>  $body
     */
    private function requireKeys(array $body): void
    {
        if (($body['inspectionObjectCode'] ?? null) === null
            || ($body['inspectionParameterCode'] ?? null) === null
            || ($body['judgmentStandardCode'] ?? null) === null) {
            throw new InvalidArgumentException(
                'inspectionObjectCode/inspectionParameterCode/judgmentStandardCode are required'
            );
        }
    }

    private function found(
        string $objectCode,
        string $parameterCode,
        string $standardCode
    ): InspectionTechnicalRequirement {
        $entry = InspectionTechnicalRequirement::query()
            ->where('tenant_id', $this->tenantId)
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->where('judgment_standard_code', $standardCode)
            ->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)
                ->setModel(InspectionTechnicalRequirement::class, ["{$objectCode}/{$parameterCode}/{$standardCode}"]);
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionTechnicalRequirement $e): array
    {
        return [
            'tenant_id' => $e->tenant_id,
            'inspection_object_code' => $e->inspection_object_code,
            'inspection_parameter_code' => $e->inspection_parameter_code,
            'judgment_standard_code' => $e->judgment_standard_code,
            'conditions' => $e->conditions,
            'value_type' => $e->value_type,
            'min_value' => $e->min_value,
            'max_value' => $e->max_value,
            'target_value' => $e->target_value,
            'expression' => $e->expression,
            'unit' => $e->unit,
            'comparison' => $e->comparison,
            'judgment_mode' => $e->judgment_mode,
            'verification_status' => $e->verification_status,
            'clause' => $e->clause,
            'source_page' => $e->source_page,
            'source_hash' => $e->source_hash,
            'brand' => $e->brand,
            'model' => $e->model,
            'grade' => $e->grade,
            'spec' => $e->spec,
            'sieve' => $e->sieve,
            'remark' => $e->remark,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
