<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TechnicalRequirement\TechnicalRequirementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M06.F06 技术要求（三元复合主键 object × parameter × judgmentStandard；tenant
// 隔离）集成测试（镜像 lab rails test/integration/technical_requirements_test.rb
// 全部 10 用例，不加戏）。list 返回裸数组（4 过滤），get/update/delete 三键 + tenant 寻址。
final class TechnicalRequirementsTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    #[Test]
    public function m06_f06_list_is_bare_array(): void
    {
        $body = $this->apiGet('/api/technical-requirements')->assertOk()->json();

        $this->assertIsList($body);
    }

    #[Test]
    public function m06_f06_create_defaults_enum_quartet(): void
    {
        $seed = $this->seedDictionary();

        $body = $this->apiPost('/api/technical-requirements', $this->trBody($seed))
            ->assertOk()->json();

        $this->assertSame($seed['object']->code, $body['inspectionObjectCode']);
        $this->assertSame($seed['standard']->code, $body['judgmentStandardCode']);
        $this->assertSame('numeric', $body['valueType']);
        $this->assertSame('≥', $body['comparison']);
        $this->assertSame('manual', $body['judgmentMode']);
        $this->assertSame('draft', $body['verificationStatus']);
        $this->assertSame(0, $body['sortOrder']);
        $this->assertSame(self::TENANT_A, $body['tenantId']);
    }

    #[Test]
    public function m06_f06_create_missing_judgment_standard_is_400(): void
    {
        $seed = $this->seedDictionary();

        $response = $this->apiPost('/api/technical-requirements', [
            'inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code,
        ]);
        $response->assertBadRequest();
        $this->assertSame('BAD_REQUEST', $response->json('code'));
    }

    #[Test]
    public function m06_f06_list_filters_and_tenant_scoping(): void
    {
        $seed = $this->seedDictionary();
        $this->createTr($seed);

        $query = ['inspectionObjectCode' => $seed['object']->code];
        $this->assertCount(1, $this->apiGet('/api/technical-requirements?'.http_build_query(
            $query + ['verificationStatus' => 'draft']
        ))->assertOk()->json());

        $this->assertCount(0, $this->apiGet('/api/technical-requirements?'.http_build_query(
            $query + ['verificationStatus' => 'verified']
        ))->json());

        // tenant 隔离：TENANT_B 查不到
        $this->assertCount(0, $this->apiGet(
            '/api/technical-requirements?'.http_build_query($query),
            self::TENANT_B
        )->json());
    }

    #[Test]
    public function m06_f06_get_by_three_keys_200_unknown_404(): void
    {
        $seed = $this->seedDictionary();
        $this->createTr($seed);

        $found = $this->apiGet($this->trPath($seed));
        $found->assertOk();
        $this->assertSame('≥ 42.5 MPa', $found->json('targetValue'));

        $missing = $this->apiGet("/api/technical-requirements/nope/{$seed['parameter']->code}/{$seed['standard']->code}");
        $missing->assertNotFound();
        $this->assertSame('NOT_FOUND', $missing->json('code'));
    }

    #[Test]
    public function m06_f06_update_unit_partial(): void
    {
        $seed = $this->seedDictionary();
        $this->createTr($seed);

        $body = $this->apiPut($this->trPath($seed), ['unit' => 'MPa'])->assertOk()->json();

        $this->assertSame('MPa', $body['unit']);
        $this->assertSame('draft', $body['verificationStatus']);
    }

    #[Test]
    public function m06_f06_update_unknown_tenant_b_is_404(): void
    {
        $seed = $this->seedDictionary();

        $this->apiPut(
            "/api/technical-requirements/nope/{$seed['parameter']->code}/{$seed['standard']->code}",
            ['unit' => 'x'],
            self::TENANT_B
        )->assertNotFound();
    }

    #[Test]
    public function m06_f06_delete_204_then_repeat_404(): void
    {
        $seed = $this->seedDictionary();
        $this->createTr($seed);

        $this->apiDelete($this->trPath($seed))->assertNoContent();
        $this->apiDelete($this->trPath($seed))->assertNotFound();
    }

    #[Test]
    public function m06_f06_without_token_is_401(): void
    {
        $this->getJson('/api/technical-requirements')->assertUnauthorized();
    }

    #[Test]
    public function m06_f06_create_duplicate_three_keys_merges(): void
    {
        $seed = $this->seedDictionary();
        $this->createTr($seed);

        $second = $this->apiPost('/api/technical-requirements',
            array_merge($this->trBody($seed), ['targetValue' => '≥ 52.5 MPa']));
        $second->assertOk();
        $this->assertSame('≥ 52.5 MPa', $second->json('targetValue'));

        $found = $this->apiGet($this->trPath($seed));
        $this->assertSame('≥ 52.5 MPa', $found->json('targetValue'));
    }

    // === private（rails tr_body / create_tr helper 同位） ===

    /**
     * @return array<string, mixed>
     */
    private function trBody(array $seed): array
    {
        return [
            'inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code,
            'judgmentStandardCode' => $seed['standard']->code,
            'requirement' => '≥ 42.5 MPa',
            'targetValue' => '≥ 42.5 MPa',
        ];
    }

    private function trPath(array $seed): string
    {
        return '/api/technical-requirements/'
            .$seed['object']->code.'/'.$seed['parameter']->code.'/'.$seed['standard']->code;
    }

    private function createTr(array $seed): void
    {
        (new TechnicalRequirementService(self::TENANT_A))->create($this->trBody($seed));
    }
}
