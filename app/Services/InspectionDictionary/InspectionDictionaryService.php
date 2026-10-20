<?php

declare(strict_types=1);

namespace App\Services\InspectionDictionary;

// M06 检测能力字典四实体门面 —— lab-springboot InspectionDictionaryService 1:1
// 镜像（接口形状不变）。实体装配拆分在同级 Specialties / Objects / Parameters /
// Standards（各自含 found/attrs/apply/dto，守住 Metrics）——rails
// InspectionDictionary::InspectionDictionaryService 的 Laravel 同构移植。
final class InspectionDictionaryService
{
    private Specialties $specialties;

    private Objects $objects;

    private Parameters $parameters;

    private Standards $standards;

    public function __construct()
    {
        $this->specialties = new Specialties;
        $this->objects = new Objects;
        $this->parameters = new Parameters;
        $this->standards = new Standards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listSpecialties(?string $keyword): array
    {
        return $this->specialties->list($keyword);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createSpecialty(array $body): array
    {
        return $this->specialties->create($body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateSpecialty(string $code, array $body): array
    {
        return $this->specialties->update($code, $body);
    }

    public function deleteSpecialty(string $code): void
    {
        $this->specialties->delete($code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listObjects(?string $keyword, ?string $specialtyCode): array
    {
        return $this->objects->list($keyword, $specialtyCode);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createObject(array $body): array
    {
        return $this->objects->create($body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateObject(string $code, array $body): array
    {
        return $this->objects->update($code, $body);
    }

    public function deleteObject(string $code): void
    {
        $this->objects->delete($code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listParameters(?string $keyword, ?string $sourceType): array
    {
        return $this->parameters->list($keyword, $sourceType);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createParameter(array $body): array
    {
        return $this->parameters->create($body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateParameter(string $code, array $body): array
    {
        return $this->parameters->update($code, $body);
    }

    public function deleteParameter(string $code): void
    {
        $this->parameters->delete($code);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listStandards(?string $keyword, ?string $status): array
    {
        return $this->standards->list($keyword, $status);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function createStandard(array $body): array
    {
        return $this->standards->create($body);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function updateStandard(string $code, array $body): array
    {
        return $this->standards->update($code, $body);
    }

    public function deleteStandard(string $code): void
    {
        $this->standards->delete($code);
    }
}
