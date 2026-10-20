<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Catalog\CatalogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M04.F06-F09 码表（catalog brands/models/specs/grades）集成测试（镜像 lab rails
// test/integration/catalog_test.rb 全部用例，kind 维度用 DataProvider 展开）。
// 对照 ct：inspection-catalog.test.ts / inspection-catalog-write.test.ts；
// 参照语义：lab-springboot CatalogService + InspectionCatalogController。
final class CatalogTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // TENANT_A / TENANT_B 常量来自 LabSupport trait

    // rails KINDS = %w[brands models specs grades]（plural path ↔ singular kind）
    private const SINGULAR = [
        'brands' => 'brand', 'models' => 'model', 'specs' => 'spec', 'grades' => 'grade',
    ];

    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        yield 'brands' => ['brands'];
        yield 'models' => ['models'];
        yield 'specs' => ['specs'];
        yield 'grades' => ['grades'];
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_list_returns_full_envelope_page_1_page_size_total(string $kind): void
    {
        $response = $this->apiGet("/api/catalog/{$kind}");

        $response->assertOk();
        $body = $response->json();
        foreach (['page', 'pageSize', 'total', 'items'] as $key) {
            $this->assertArrayHasKey($key, $body);
        }
        $this->assertSame(1, $body['page']);
        $this->assertSame($body['total'], $body['pageSize']);
        $this->assertIsArray($body['items']);
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_list_keyword_filter_hits_self_made_row(string $kind): void
    {
        $seed = $this->seedDictionary();
        $code = $this->uniqCode("ct-{$kind[0]}");
        $this->createEntry($kind, $code, "picker-{$code}", $seed['object']->code);

        $response = $this->apiGet("/api/catalog/{$kind}?keyword=".urlencode($code));

        $response->assertOk();
        $body = $response->json();
        $this->assertSame(1, $body['total']);
        $this->assertSame($code, $body['items'][0]['code']);
        $this->assertSame("picker-{$code}", $body['items'][0]['name']);
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_create_returns_200_full_fields_sort_order_defaults_zero(string $kind): void
    {
        $seed = $this->seedDictionary();
        $code = $this->uniqCode("ct-{$kind[0]}");

        $response = $this->apiPost("/api/catalog/{$kind}", [
            'code' => $code, 'name' => "entry {$code}", 'inspectionObjectCode' => $seed['object']->code,
        ]);

        $response->assertOk();
        $body = $response->json();
        $this->assertSame($code, $body['code']);
        $this->assertSame("entry {$code}", $body['name']);
        $this->assertSame($seed['object']->code, $body['inspectionObjectCode']);
        $this->assertSame(0, $body['sortOrder']);
        $this->assertSame(self::TENANT_A, $body['tenantId']);
        $this->assertNotEmpty($body['createdAt']);
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_create_without_code_is_400_bad_request(string $kind): void
    {
        $response = $this->apiPost("/api/catalog/{$kind}", ['name' => 'no-code']);

        $response->assertBadRequest();
        $this->assertSame('BAD_REQUEST', $response->json('code'));
    }

    // 401 用例放两段 DataProvider 组之间（rails 同位）
    #[Test]
    public function m04_create_duplicate_code_hits_unique_index_is_400_bad_request_not_500(): void
    {
        $seed = $this->seedDictionary();
        $code = $this->uniqCode('ct-dup');

        $first = $this->apiPost('/api/catalog/brands', [
            'code' => $code, 'name' => "first {$code}", 'inspectionObjectCode' => $seed['object']->code,
        ]);
        $first->assertOk();

        // 家族真源：lab-springboot GlobalExceptionHandler DataIntegrityViolationException → 400
        // （2026-09-29 review Important：RecordNotUnique 未 rescue 曾 500）
        $dup = $this->apiPost('/api/catalog/brands', [
            'code' => $code, 'name' => "dup {$code}", 'inspectionObjectCode' => $seed['object']->code,
        ]);
        $dup->assertBadRequest();
        $this->assertSame('BAD_REQUEST', $dup->json('code'));
    }

    #[Test]
    public function m04_without_token_is_401(): void
    {
        $this->getJson('/api/catalog/brands')->assertUnauthorized();
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_update_renames_partial_fields(string $kind): void
    {
        $code = $this->uniqCode("ct-{$kind[0]}");
        $this->createEntry($kind, $code, 'old');

        $response = $this->apiPut("/api/catalog/{$kind}/{$code}", ['name' => 'renamed']);

        $response->assertOk();
        $this->assertSame('renamed', $response->json('name'));
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_get_unknown_code_is_404_rails_verbatim_message(string $kind): void
    {
        // rails `<label> not found: <code>`（CatalogService KINDS label）——5.117 清理批 ②
        // （{kind}/{code} 无 GET show，miss 断言走 PUT 面）
        $labels = ['brands' => 'Brand', 'models' => 'Model', 'specs' => 'Spec', 'grades' => 'Grade'];

        $response = $this->apiPut("/api/catalog/{$kind}/no-such-code", ['name' => 'x']);

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
        $this->assertSame("{$labels[$kind]} not found: no-such-code", $response->json('message'));
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_update_unknown_code_is_404(string $kind): void
    {
        $response = $this->apiPut("/api/catalog/{$kind}/no-such-code", ['name' => 'x']);

        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_delete_is_204_then_repeat_is_404(string $kind): void
    {
        $code = $this->uniqCode("ct-{$kind[0]}");
        $this->createEntry($kind, $code, 'doomed');

        $this->apiDelete("/api/catalog/{$kind}/{$code}")->assertNoContent();
        $this->apiDelete("/api/catalog/{$kind}/{$code}")->assertNotFound();
    }

    #[DataProvider('kinds')]
    #[Test]
    public function m04_tenant_isolation_tenant_b_cannot_see_or_steal_tenant_a_row(string $kind): void
    {
        $code = $this->uniqCode("ct-{$kind[0]}");
        $this->createEntry($kind, $code, 'mine');

        $list = $this->apiGet("/api/catalog/{$kind}?keyword=".urlencode($code), self::TENANT_B);
        $list->assertOk();
        $this->assertSame(0, $list->json('total'));

        $steal = $this->apiPut("/api/catalog/{$kind}/{$code}", ['name' => 'steal'], self::TENANT_B);
        $steal->assertNotFound();
    }

    // ==== 助手（rails LabSupport#create_entry 同位——service 直造，不过 HTTP） ====

    #[DataProvider('kinds')]
    #[Test]
    public function m04_create_false_sort_order_falls_back_zero(string $kind): void
    {
        // rails entry_attrs `body['sortOrder'] || 0`（Ruby 真值）——5.117 清理批 ③
        $code = $this->uniqCode("ct-{$kind[0]}");

        $entry = (new CatalogService)->create(self::SINGULAR[$kind], self::TENANT_A,
            ['code' => $code, 'name' => 'x', 'sortOrder' => false]);

        $this->assertSame(0, $entry['sort_order']);
    }

    /**
     * @return array<string, mixed>
     */
    private function createEntry(string $kind, string $code, string $name, ?string $objectCode = null): array
    {
        return (new CatalogService)->create(
            self::SINGULAR[$kind],
            self::TENANT_A,
            ['code' => $code, 'name' => $name, 'inspectionObjectCode' => $objectCode]
        );
    }
}
