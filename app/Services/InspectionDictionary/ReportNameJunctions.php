<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionObjectReportName;
use App\Models\InspectionReportNameParameter;
use App\Models\InspectionReportNameStandard;

// M06.F07 报告名称相关的 3 组 junction —— 自 InspectionJunctionService 拆出的
// trait（rails InspectionDictionary::ReportNameJunctions 同构移植）：
// object↔report-name / report-name↔standard(role) / report-name↔parameter。
// link = upsert 整行覆盖含 created_at；unlink 幂等不校验键；
// link 键 requireNonBlank（null/blank -> IAE 400）。
trait ReportNameJunctions
{
    // === object ↔ report-name ===

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkObjectReportName(array $body): void
    {
        $this->requireNonBlank($body['inspectionObjectCode'] ?? null, $body['reportNameCode'] ?? null);
        $this->upsert(InspectionObjectReportName::class,
            ['inspection_object_code' => $body['inspectionObjectCode'],
                'report_name_code' => $body['reportNameCode']],
            ['remark' => $body['remark'] ?? null]);
    }

    public function unlinkObjectReportName(?string $objectCode, ?string $reportNameCode): void
    {
        $this->deleteByKeys(InspectionObjectReportName::class, [
            'inspection_object_code' => $objectCode,
            'report_name_code' => $reportNameCode,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listObjectReportNameLinks(?string $objectCode, ?string $reportNameCode): array
    {
        $query = $this->filter(InspectionObjectReportName::query(),
            ['inspection_object_code' => $objectCode]);
        $query = $this->filter($query, ['report_name_code' => $reportNameCode]);

        return $query->get()
            ->map(fn (InspectionObjectReportName $e): array => [
                'inspection_object_code' => $e->inspection_object_code,
                'report_name_code' => $e->report_name_code,
                'remark' => $e->remark,
            ])
            ->all();
    }

    // === report-name ↔ standard (role) ===

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkReportNameStandard(array $body): void
    {
        $this->requireNonBlank($body['reportNameCode'] ?? null,
            $body['inspectionStandardCode'] ?? null, $body['role'] ?? null);
        $this->upsert(InspectionReportNameStandard::class,
            ['report_name_code' => $body['reportNameCode'],
                'inspection_standard_code' => $body['inspectionStandardCode'],
                'role' => $body['role']],
            ['remark' => $body['remark'] ?? null]);
    }

    public function unlinkReportNameStandard(?string $reportNameCode, ?string $standardCode, ?string $role): void
    {
        $this->deleteByKeys(InspectionReportNameStandard::class, [
            'report_name_code' => $reportNameCode,
            'inspection_standard_code' => $standardCode,
            'role' => $role,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReportNameStandardLinks(?string $reportNameCode, ?string $role): array
    {
        $query = $this->filter(InspectionReportNameStandard::query(),
            ['report_name_code' => $reportNameCode]);
        if ($this->presentValue($role)) {
            $query = $query->where('role', $role);
        }

        return $query->get()
            ->map(fn (InspectionReportNameStandard $e): array => [
                'report_name_code' => $e->report_name_code,
                'inspection_standard_code' => $e->inspection_standard_code,
                'role' => $e->role,
                'remark' => $e->remark,
            ])
            ->all();
    }

    // === report-name ↔ parameter ===

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkReportNameParameter(array $body): void
    {
        $this->requireNonBlank($body['reportNameCode'] ?? null, $body['inspectionParameterCode'] ?? null);
        $this->upsert(InspectionReportNameParameter::class,
            ['report_name_code' => $body['reportNameCode'],
                'inspection_parameter_code' => $body['inspectionParameterCode']],
            ['remark' => $body['remark'] ?? null]);
    }

    public function unlinkReportNameParameter(?string $reportNameCode, ?string $parameterCode): void
    {
        $this->deleteByKeys(InspectionReportNameParameter::class, [
            'report_name_code' => $reportNameCode,
            'inspection_parameter_code' => $parameterCode,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listReportNameParameterLinks(?string $reportNameCode, ?string $parameterCode): array
    {
        $query = $this->filter(InspectionReportNameParameter::query(),
            ['report_name_code' => $reportNameCode]);
        $query = $this->filter($query, ['inspection_parameter_code' => $parameterCode]);

        return $query->get()
            ->map(fn (InspectionReportNameParameter $e): array => [
                'report_name_code' => $e->report_name_code,
                'inspection_parameter_code' => $e->inspection_parameter_code,
                'remark' => $e->remark,
            ])
            ->all();
    }
}
