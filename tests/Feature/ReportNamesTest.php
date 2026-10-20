<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ReportNames\InspectionReportNameService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M06.F07 报告名称（平台级字典 CRUD :code + 3 类 junction link）集成测试
// （镜像 lab rails test/integration/report_names_test.rb 全部 12 用例，不加戏）。
// 对照 ct：report-names.test.ts / report-names-write.test.ts；
// 参照语义：lab-springboot InspectionReportNameService/Mapper + InspectionJunctionService。
final class ReportNamesTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    #[Test]
    public function m06_f07_list_envelope_page1_pagesize_total(): void
    {
        $body = $this->apiGet('/api/report-names')->assertOk()->json();
        foreach (['page', 'pageSize', 'total', 'items'] as $key) {
            $this->assertArrayHasKey($key, $body, "缺 {$key}");
        }
        $this->assertSame(1, $body['page']);
        $this->assertSame($body['total'], $body['pageSize']);
        $this->assertIsArray($body['items']);
    }

    #[Test]
    public function m06_f07_list_keyword_filters_code_and_name(): void
    {
        $code = $this->uniqCode('ct-rn');
        $this->createRn($code, "needle-{$code}");

        $body = $this->apiGet('/api/report-names?keyword='.urlencode($code))->assertOk()->json();
        $this->assertSame(1, $body['total']);
        $this->assertSame($code, $body['items'][0]['code']);
        $this->assertSame([], $body['items'][0]['extFields'], 'extFields 缺省 []');
    }

    #[Test]
    public function m06_f07_get_unknown_code_is_404(): void
    {
        $response = $this->apiGet('/api/report-names/no-such-rn');
        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
    }

    #[Test]
    public function m06_f07_create_echoes_ext_fields_array_and_default_sort_order(): void
    {
        $code = $this->uniqCode('ct-rn');

        $create = $this->apiPost('/api/report-names', [
            'code' => $code,
            'name' => "rn {$code}",
            'extFields' => [['key' => 'pH', 'label' => 'pH 值']],
        ]);
        $create->assertOk();
        $body = $create->json();
        $this->assertSame($code, $body['code']);
        $this->assertSame([['key' => 'pH', 'label' => 'pH 值']], $body['extFields']);
        $this->assertSame(0, $body['sortOrder']);
    }

    #[Test]
    public function m06_f07_create_without_name_is_400(): void
    {
        $this->apiPost('/api/report-names', [
            'code' => $this->uniqCode('ct-rn'),
        ])->assertBadRequest();
    }

    #[Test]
    public function m06_f07_update_renames(): void
    {
        $code = $this->uniqCode('ct-rn');
        $this->createRn($code);

        $update = $this->apiPut("/api/report-names/{$code}", ['name' => 'renamed']);
        $update->assertOk();
        $this->assertSame('renamed', $update->json('name'));
    }

    #[Test]
    public function m06_f07_delete_is_204_then_repeat_is_404(): void
    {
        $code = $this->uniqCode('ct-rn');
        $this->createRn($code);

        $this->apiDelete("/api/report-names/{$code}")->assertNoContent();
        $this->apiDelete("/api/report-names/{$code}")->assertNotFound();
    }

    // === 3 类 junction link/unlink/list ===

    #[Test]
    public function m06_f07_object_link_204_unlink_idempotent_list_envelope(): void
    {
        $seed = $this->seedDictionary();
        $rn = $this->uniqCode('ct-rn');
        $this->createRn($rn);

        $this->postJson('/api/report-names/links/object', [
            'inspectionObjectCode' => $seed['object']->code, 'reportNameCode' => $rn,
        ], $this->authHeader())->assertNoContent();

        $list = $this->getJson('/api/report-names/links/object?'.http_build_query([
            'inspectionObjectCode' => $seed['object']->code, 'reportNameCode' => $rn,
        ]), $this->authHeader());
        $list->assertOk();
        $body = $list->json();
        $this->assertSame(1, $body['total']);
        $this->assertSame(1, $body['page']);
        $this->assertSame($rn, $body['items'][0]['reportNameCode']);

        $this->deleteJson('/api/report-names/links/object', [
            'inspectionObjectCode' => $seed['object']->code, 'reportNameCode' => $rn,
        ], $this->authHeader())->assertNoContent();

        // 幂等：重复 unlink 未命中同样 204
        $this->deleteJson('/api/report-names/links/object', [
            'inspectionObjectCode' => $seed['object']->code, 'reportNameCode' => $rn,
        ], $this->authHeader())->assertNoContent();
    }

    #[Test]
    public function m06_f07_parameter_link_unlink_204_and_missing_field_400(): void
    {
        $seed = $this->seedDictionary();
        $rn = $this->uniqCode('ct-rn');
        $this->createRn($rn);

        $this->postJson('/api/report-names/links/parameter', [
            'reportNameCode' => $rn, 'inspectionParameterCode' => $seed['parameter']->code,
        ], $this->authHeader())->assertNoContent();

        $this->postJson('/api/report-names/links/parameter', [
            'reportNameCode' => $rn,
        ], $this->authHeader())->assertBadRequest();

        $this->deleteJson('/api/report-names/links/parameter', [
            'reportNameCode' => $rn, 'inspectionParameterCode' => $seed['parameter']->code,
        ], $this->authHeader())->assertNoContent();
    }

    #[Test]
    public function m06_f07_standard_link_role_required_unlink_idempotent_list_filters_by_role(): void
    {
        $seed = $this->seedDictionary();
        $rn = $this->uniqCode('ct-rn');
        $this->createRn($rn);
        $body = ['reportNameCode' => $rn,
            'inspectionStandardCode' => $seed['standard']->code, 'role' => 'JUDGMENT'];

        $this->postJson('/api/report-names/links/standard', $body, $this->authHeader())
            ->assertNoContent();

        $judgment = $this->apiGet('/api/report-names/links/standard?'.http_build_query([
            'reportNameCode' => $rn, 'role' => 'JUDGMENT',
        ]));
        $judgment->assertOk();
        $this->assertSame(1, $judgment->json('total'));

        $testing = $this->apiGet('/api/report-names/links/standard?'.http_build_query([
            'reportNameCode' => $rn, 'role' => 'TESTING',
        ]));
        $testing->assertOk();
        $this->assertSame(0, $testing->json('total'));

        $this->deleteJson('/api/report-names/links/standard', $body, $this->authHeader())
            ->assertNoContent();
        $this->deleteJson('/api/report-names/links/standard', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m06_f07_without_token_is_401(): void
    {
        $this->getJson('/api/report-names')->assertUnauthorized();
    }

    #[Test]
    public function m06_f07_create_duplicate_code_merges_overwrite(): void
    {
        // 共库四方对拍契约：nextjs/springboot 先建的行，rails/laravel 再 create 同
        // code 必须覆盖而非 UniqueViolation 500（2026-09-29 live run2/3 实锤）。
        $code = $this->uniqCode('ct-rn');
        $this->createRn($code, "v1-{$code}");

        $create = $this->apiPost('/api/report-names', [
            'code' => $code, 'name' => "v2-{$code}", 'sortOrder' => 7,
        ]);
        $create->assertOk();
        $body = $create->json();
        $this->assertSame("v2-{$code}", $body['name']);
        $this->assertSame(7, $body['sortOrder']);

        $this->assertSame("v2-{$code}", $this->apiGet("/api/report-names/{$code}")->json('name'));
    }

    // === private（rails create_rn helper 同位） ===

    private function createRn(string $code, ?string $name = null): string
    {
        (new InspectionReportNameService)->create([
            'code' => $code, 'name' => $name ?? "rn {$code}",
        ]);

        return $code;
    }
}
