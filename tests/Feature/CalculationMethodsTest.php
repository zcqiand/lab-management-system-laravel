<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\CalculationMethod\CalculationMethodService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M06.F05 计算方法（复合主键 objectCode × parameterCode；平台级字典无 tenant）
// 集成测试（镜像 lab rails test/integration/calculation_methods_test.rb 全部
// 10 用例，不加戏）。list 返回裸数组（不分页信封）。
final class CalculationMethodsTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    #[Test]
    public function m06_f05_list_is_bare_array_no_envelope(): void
    {
        $body = $this->apiGet('/api/calculation-methods')->assertOk()->json();

        $this->assertIsList($body);
    }

    #[Test]
    public function m06_f05_list_filters_by_object_and_parameter(): void
    {
        $seed = $this->seedDictionary();
        $this->createCm($seed['object']->code, $seed['parameter']->code);

        $body = $this->apiGet('/api/calculation-methods?'.http_build_query([
            'inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code,
        ]))->assertOk()->json();

        $this->assertCount(1, $body);
        $this->assertSame($seed['object']->code, $body[0]['inspectionObjectCode']);
        $this->assertSame('value * 2', $body[0]['formula']);
        $this->assertSame('manual', $body[0]['algorithmType']);
        $this->assertSame(1, $body[0]['specimenCount']);
    }

    #[Test]
    public function m06_f05_get_by_composite_key_200_unknown_404(): void
    {
        $seed = $this->seedDictionary();
        $this->createCm($seed['object']->code, $seed['parameter']->code);

        $found = $this->apiGet("/api/calculation-methods/{$seed['object']->code}/{$seed['parameter']->code}");
        $found->assertOk();
        $this->assertSame($seed['parameter']->code, $found->json('inspectionParameterCode'));

        $missing = $this->apiGet("/api/calculation-methods/nope/{$seed['parameter']->code}");
        $missing->assertNotFound();
        $this->assertSame('NOT_FOUND', $missing->json('code'));
    }

    #[Test]
    public function m06_f05_create_defaults_manual_1_0(): void
    {
        $seed = $this->seedDictionary();

        $body = $this->apiPost('/api/calculation-methods', [
            'inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code,
            'formula' => 'value * 2',
        ])->assertOk()->json();

        $this->assertSame($seed['object']->code, $body['inspectionObjectCode']);
        $this->assertSame('value * 2', $body['formula']);
        $this->assertSame('manual', $body['algorithmType']);
        $this->assertSame(1, $body['specimenCount']);
        $this->assertSame(0, $body['sortOrder']);
    }

    #[Test]
    public function m06_f05_create_duplicate_key_merges(): void
    {
        $seed = $this->seedDictionary();
        $key = ['inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code];

        $this->apiPost('/api/calculation-methods', $key + ['formula' => 'value * 2'])->assertOk();

        $second = $this->apiPost('/api/calculation-methods', $key + ['formula' => 'value * 3']);
        $second->assertOk();
        $this->assertSame('value * 3', $second->json('formula'));

        $found = $this->apiGet("/api/calculation-methods/{$seed['object']->code}/{$seed['parameter']->code}");
        $this->assertSame('value * 3', $found->json('formula'));
    }

    #[Test]
    public function m06_f05_create_missing_parameter_code_is_400(): void
    {
        $seed = $this->seedDictionary();

        $response = $this->apiPost('/api/calculation-methods', [
            'inspectionObjectCode' => $seed['object']->code,
        ]);
        $response->assertBadRequest();
        $this->assertSame('BAD_REQUEST', $response->json('code'));
    }

    #[Test]
    public function m06_f05_update_formula_partial_keep_primary_key(): void
    {
        $seed = $this->seedDictionary();
        $this->createCm($seed['object']->code, $seed['parameter']->code);

        $body = $this->apiPut(
            "/api/calculation-methods/{$seed['object']->code}/{$seed['parameter']->code}",
            ['formula' => 'renamed']
        )->assertOk()->json();

        $this->assertSame('renamed', $body['formula']);
        $this->assertSame('manual', $body['algorithmType']);
    }

    #[Test]
    public function m06_f05_update_unknown_is_404(): void
    {
        $this->apiPut('/api/calculation-methods/nope/nope2', ['formula' => 'x'])
            ->assertNotFound();
    }

    #[Test]
    public function m06_f05_delete_204_then_repeat_404(): void
    {
        $seed = $this->seedDictionary();
        $this->createCm($seed['object']->code, $seed['parameter']->code);
        $path = "/api/calculation-methods/{$seed['object']->code}/{$seed['parameter']->code}";

        $this->apiDelete($path)->assertNoContent();
        $this->apiDelete($path)->assertNotFound();
    }

    #[Test]
    public function m06_f05_without_token_is_401(): void
    {
        $this->getJson('/api/calculation-methods')->assertUnauthorized();
    }

    // === private（rails create_cm helper 同位） ===

    private function createCm(string $objectCode, string $parameterCode): void
    {
        (new CalculationMethodService)->create([
            'inspectionObjectCode' => $objectCode,
            'inspectionParameterCode' => $parameterCode,
            'formula' => 'value * 2',
        ]);
    }
}
