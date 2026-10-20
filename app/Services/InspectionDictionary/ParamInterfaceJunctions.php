<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

use App\Models\InspectionParamInterfaceLink;

// M10 parameter ↔ param-interface junction（config jsonb）—— 自
// InspectionJunctionService 拆出的 trait（rails
// InspectionDictionary::ParamInterfaceJunctions 同构移植）。
trait ParamInterfaceJunctions
{
    // @impl M06.F08.I06 (book anchor xr-know-012)

    /**
     * @param  array<string, mixed>  $body
     */
    public function linkParamInterface(array $body): void
    {
        $this->requireNonBlank($body['inspectionParameterCode'] ?? null, $body['paramInterfaceCode'] ?? null);
        // config 是 jsonb 列：updateOrInsert 走查询构造器绑定（无 Eloquent cast），
        // 数组须先行 json_encode（Array to string conversion 实锤）；null 原样传
        $config = $body['config'] ?? null;
        $this->upsert(InspectionParamInterfaceLink::class,
            ['inspection_parameter_code' => $body['inspectionParameterCode'],
                'param_interface_code' => $body['paramInterfaceCode']],
            ['report_name_code' => $body['reportNameCode'] ?? null,
                'config' => is_array($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : $config]);
    }

    public function unlinkParamInterface(?string $parameterCode, ?string $interfaceCode): void
    {
        $this->deleteByKeys(InspectionParamInterfaceLink::class, [
            'inspection_parameter_code' => $parameterCode,
            'param_interface_code' => $interfaceCode,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listParamInterfaceLinks(?string $parameterCode, ?string $interfaceCode): array
    {
        $query = $this->filter(InspectionParamInterfaceLink::query(),
            ['inspection_parameter_code' => $parameterCode]);
        $query = $this->filter($query, ['param_interface_code' => $interfaceCode]);

        return $query->get()
            ->map(fn (InspectionParamInterfaceLink $e): array => [
                'inspection_parameter_code' => $e->inspection_parameter_code,
                'param_interface_code' => $e->param_interface_code,
                'report_name_code' => $e->report_name_code,
                'config' => $e->config,
            ])
            ->all();
    }
}
