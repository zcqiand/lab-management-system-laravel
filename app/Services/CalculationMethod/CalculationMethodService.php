<?php

declare(strict_types=1);

namespace App\Services\CalculationMethod;

use App\Models\InspectionCalculationMethod;
use App\Services\UpdateFields;
use App\Support\Time;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

// M06.F05 计算方法 —— lab-springboot CalculationMethodService +
// CalculationMethodMapper 1:1 镜像（rails CalculationMethod::CalculationMethodService
// 同构移植）。平台级字典（无 tenant_id，per V012 备注）。
// 复合主键 (inspection_object_code, inspection_parameter_code)；
// list 双过滤 + 排序 sort_order, object, parameter。
final class CalculationMethodService
{
    use UpdateFields;

    // 镜像 CalculationMethodMapper.applyUpdate 的字段判定
    private const UPDATE_FIELDS = [
        'testingStandardCode' => ['testing_standard_code', 'truthy'],
        'reportNameCode' => ['report_name_code', 'truthy'],
        'algorithmType' => ['algorithm_type', 'truthy'],
        'specimenCount' => ['specimen_count', 'strict'],
        'formula' => ['formula', 'truthy'],
        'conditions' => ['conditions', 'truthy'],
        'roundingRule' => ['rounding_rule', 'truthy'],
        'remark' => ['remark', 'truthy'],
        'sortOrder' => ['sort_order', 'strict'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $objectCode, ?string $parameterCode): array
    {
        $query = InspectionCalculationMethod::query();
        if ($objectCode !== null && $objectCode !== '') {
            $query->where('inspection_object_code', $objectCode);
        }
        if ($parameterCode !== null && $parameterCode !== '') {
            $query->where('inspection_parameter_code', $parameterCode);
        }

        return $query->orderBy('sort_order')
            ->orderBy('inspection_object_code')
            ->orderBy('inspection_parameter_code')
            ->get()
            ->map(fn (InspectionCalculationMethod $e): array => $this->dto($e))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $objectCode, string $parameterCode): array
    {
        return $this->dto($this->found($objectCode, $parameterCode));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        $objectCode = $body['inspectionObjectCode'] ?? null;
        $parameterCode = $body['inspectionParameterCode'] ?? null;
        if ($objectCode === null || $parameterCode === null) {
            throw new InvalidArgumentException('inspectionObjectCode and inspectionParameterCode are required');
        }

        $now = Time::nowIso();
        // springboot repo.save = JPA merge（同 PK 覆盖整行）——共库四方对拍下
        // 裸 insert 会 UniqueViolation 500（2026-09-29 live run2/3 实锤）。
        // 复合 PK 模型 scaffold 口径 primaryKey=null（禁单键模型写路径），走查询
        // 构造器 updateOrInsert：exists → UPDATE 全列，miss → INSERT（含 created_at
        // 恒刷，与 rails find_or_new + 全列 assign 同语义）。
        InspectionCalculationMethod::query()
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->updateOrInsert([
                'inspection_object_code' => $objectCode,
                'inspection_parameter_code' => $parameterCode,
            ], [
                'testing_standard_code' => $body['testingStandardCode'] ?? null,
                'report_name_code' => $body['reportNameCode'] ?? null,
                // 镜像 CalculationMethodMapper.fromCreate 的枚举缺省
                // algorithmType=manual / specimenCount=1 / sortOrder=0
                'algorithm_type' => $this->defaultFor($body, 'algorithmType', 'manual'),
                'specimen_count' => $body['specimenCount'] ?? 1,
                'formula' => $body['formula'] ?? null,
                'conditions' => $body['conditions'] ?? null,
                'rounding_rule' => $body['roundingRule'] ?? null,
                'remark' => $body['remark'] ?? null,
                'sort_order' => $body['sortOrder'] ?? 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        return $this->dto($this->found($objectCode, $parameterCode));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(string $objectCode, string $parameterCode, array $body): array
    {
        $entry = $this->found($objectCode, $parameterCode);
        // 镜像 CalculationMethodMapper.applyUpdate：字段级判定跳过，updated_at 恒刷新。
        // 复合 PK 禁模型级 save()（primaryKey=null 无 where 定位键），字段装配在
        // 读出实例上算，落库走查询构造器 where 全键显式定位（scaffold 同款口径）。
        $this->applyFields($entry, $body, self::UPDATE_FIELDS);
        $entry->updated_at = Time::nowIso();
        InspectionCalculationMethod::query()
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->update($entry->getAttributes());

        return $this->dto($entry);
    }

    public function delete(string $objectCode, string $parameterCode): void
    {
        $this->found($objectCode, $parameterCode);
        InspectionCalculationMethod::query()
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->delete();
    }

    private function found(string $objectCode, string $parameterCode): InspectionCalculationMethod
    {
        $entry = InspectionCalculationMethod::query()
            ->where('inspection_object_code', $objectCode)
            ->where('inspection_parameter_code', $parameterCode)
            ->first();
        if ($entry === null) {
            throw (new ModelNotFoundException)
                ->setModel(InspectionCalculationMethod::class, ["{$objectCode}/{$parameterCode}"]);
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dto(InspectionCalculationMethod $e): array
    {
        return [
            'inspection_object_code' => $e->inspection_object_code,
            'inspection_parameter_code' => $e->inspection_parameter_code,
            'testing_standard_code' => $e->testing_standard_code,
            'report_name_code' => $e->report_name_code,
            'algorithm_type' => $e->algorithm_type,
            'specimen_count' => $e->specimen_count,
            'formula' => $e->formula,
            'conditions' => $e->conditions,
            'rounding_rule' => $e->rounding_rule,
            'remark' => $e->remark,
            'sort_order' => $e->sort_order,
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
