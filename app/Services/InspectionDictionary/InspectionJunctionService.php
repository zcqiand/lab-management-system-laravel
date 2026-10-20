<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionObjectParameter;
use App\Models\InspectionObjectStandard;
use App\Models\InspectionSpecialtyObject;
use App\Models\InspectionStandardParameter;

// M06 检测能力字典 junction link/unlink/list —— lab-springboot
// InspectionJunctionService 1:1 镜像（B6/B7）。4 组核心在本类；报告名称 3 组
// 在 ReportNameJunctions trait（rails 同名 module include 同位）；参数接口 1 组
// 随 param-interfaces 任务接入。
//
// - link 全部 upsert（同 PK 重复时整行覆盖，含 created_at —— Hibernate merge 语义）
// - unlink 幂等 204：命中才删，未命中静默 no-op（REQ-2026-001 四方一致），不校验键
// - link 端点关键键 requireNonBlank（null/blank -> IAE 400）
// - list 按 code/role 过滤（blank = 不过滤），DTO 不含时间戳
final class InspectionJunctionService
{
    use JunctionHelpers;
    use ReportNameJunctions;

    // === specialty ↔ object ===

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkSpecialtyObject(array $body): void
    {
        $this->requireNonBlank($body['inspectionSpecialtyCode'] ?? null, $body['inspectionObjectCode'] ?? null);
        $this->upsert(InspectionSpecialtyObject::class,
            ['inspection_specialty_code' => $body['inspectionSpecialtyCode'],
                'inspection_object_code' => $body['inspectionObjectCode']],
            ['remark' => $body['remark'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function unlinkSpecialtyObject(array $body): void
    {
        $this->deleteByKeys(InspectionSpecialtyObject::class, [
            'inspection_specialty_code' => $body['inspectionSpecialtyCode'] ?? null,
            'inspection_object_code' => $body['inspectionObjectCode'] ?? null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSpecialtyObjectLinks(?string $specialtyCode): array
    {
        return $this->filter(InspectionSpecialtyObject::query(),
            ['inspection_specialty_code' => $specialtyCode])
            ->get()
            ->map(fn (InspectionSpecialtyObject $e): array => [
                'inspection_specialty_code' => $e->inspection_specialty_code,
                'inspection_object_code' => $e->inspection_object_code,
                'remark' => $e->remark,
            ])
            ->all();
    }

    // === object ↔ parameter ===

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkObjectParameter(array $body): void
    {
        $this->requireNonBlank($body['inspectionObjectCode'] ?? null, $body['inspectionParameterCode'] ?? null);
        $this->upsert(InspectionObjectParameter::class,
            ['inspection_object_code' => $body['inspectionObjectCode'],
                'inspection_parameter_code' => $body['inspectionParameterCode']],
            ['qualification_level' => $body['qualificationLevel'] ?? 'QUALIFIED',
                'source_page' => $body['sourcePage'] ?? null,
                'remark' => $body['remark'] ?? null]);
    }

    public function unlinkObjectParameter(?string $objectCode, ?string $parameterCode): void
    {
        $this->deleteByKeys(InspectionObjectParameter::class, [
            'inspection_object_code' => $objectCode,
            'inspection_parameter_code' => $parameterCode,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listObjectParameterLinks(?string $objectCode, ?string $parameterCode): array
    {
        $query = $this->filter(InspectionObjectParameter::query(),
            ['inspection_object_code' => $objectCode]);
        $query = $this->filter($query, ['inspection_parameter_code' => $parameterCode]);

        return $query->get()
            ->map(fn (InspectionObjectParameter $e): array => [
                'inspection_object_code' => $e->inspection_object_code,
                'inspection_parameter_code' => $e->inspection_parameter_code,
                'qualification_level' => $e->qualification_level,
                'source_page' => $e->source_page,
                'remark' => $e->remark,
            ])
            ->all();
    }

    // === object ↔ standard (role) ===

    // @impl M06.F01.I05 (book anchor xr-know-012)
    /**
     * @param  array<string, mixed>  $body
     */
    public function linkObjectStandard(array $body): void
    {
        $this->requireNonBlank($body['inspectionObjectCode'] ?? null,
            $body['inspectionStandardCode'] ?? null, $body['role'] ?? null);
        $this->upsert(InspectionObjectStandard::class,
            ['inspection_object_code' => $body['inspectionObjectCode'],
                'inspection_standard_code' => $body['inspectionStandardCode'],
                'role' => $body['role']],
            ['remark' => $body['remark'] ?? null]);
    }

    // @impl M06.F01.I06 (book anchor xr-know-012)
    public function unlinkObjectStandard(?string $objectCode, ?string $standardCode, ?string $role): void
    {
        $this->deleteByKeys(InspectionObjectStandard::class, [
            'inspection_object_code' => $objectCode,
            'inspection_standard_code' => $standardCode,
            'role' => $role,
        ]);
    }

    // @impl M06.F01.I07 (book anchor xr-know-012)
    /**
     * @return list<array<string, mixed>>
     */
    public function listObjectStandardLinks(?string $objectCode, ?string $role): array
    {
        $query = $this->filter(InspectionObjectStandard::query(),
            ['inspection_object_code' => $objectCode]);
        if ($this->presentValue($role)) {
            $query = $query->where('role', $role);
        }

        return $query->get()
            ->map(fn (InspectionObjectStandard $e): array => [
                'inspection_object_code' => $e->inspection_object_code,
                'inspection_standard_code' => $e->inspection_standard_code,
                'role' => $e->role,
                'remark' => $e->remark,
            ])
            ->all();
    }

    // === standard ↔ parameter ===

    // @impl M06.F03.I05 (book anchor xr-know-012)
    /**
     * @param  array<string, mixed>  $body
     */
    public function linkStandardParameter(array $body): void
    {
        $this->requireNonBlank($body['inspectionStandardCode'] ?? null, $body['inspectionParameterCode'] ?? null);
        $this->upsert(InspectionStandardParameter::class,
            ['inspection_standard_code' => $body['inspectionStandardCode'],
                'inspection_parameter_code' => $body['inspectionParameterCode']],
            []);
    }

    // @impl M06.F03.I06 (book anchor xr-know-012)
    /**
     * @param  array<string, mixed>  $body
     */
    public function unlinkStandardParameter(array $body): void
    {
        $this->deleteByKeys(InspectionStandardParameter::class, [
            'inspection_standard_code' => $body['inspectionStandardCode'] ?? null,
            'inspection_parameter_code' => $body['inspectionParameterCode'] ?? null,
        ]);
    }

    // @impl M06.F03.I08 (book anchor xr-know-012)
    /**
     * @return list<array<string, mixed>>
     */
    public function listStandardParameterLinks(?string $standardCode, ?string $parameterCode): array
    {
        $query = $this->filter(InspectionStandardParameter::query(),
            ['inspection_standard_code' => $standardCode]);
        $query = $this->filter($query, ['inspection_parameter_code' => $parameterCode]);

        return $query->get()
            ->map(fn (InspectionStandardParameter $e): array => [
                'inspection_standard_code' => $e->inspection_standard_code,
                'inspection_parameter_code' => $e->inspection_parameter_code,
            ])
            ->all();
    }
}
