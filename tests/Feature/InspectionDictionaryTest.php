<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M06 检测字典 4 实体（specialties/objects/parameters/standards）CRUD +
// 4 类 junction link/unlink/list 集成测试（镜像 lab rails
// test/integration/inspection_dictionary_test.rb 全部 14 用例，不加戏）。
// 对照 ct：inspection-dictionary.test.ts / inspection-dictionary-write.test.ts；
// 参照语义：lab-springboot InspectionDictionaryService/Mapper + InspectionJunctionService。
final class InspectionDictionaryTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    // === specialties ===

    #[Test]
    public function m06_f01_specialty_list_envelope_then_create_update_delete(): void
    {
        $list = $this->apiGet('/api/inspection/specialties');
        $list->assertOk();
        $body = $list->json();
        foreach (['page', 'pageSize', 'total', 'items'] as $key) {
            $this->assertArrayHasKey($key, $body, "缺 {$key}");
        }
        $this->assertSame(1, $body['page']);

        $code = $this->uniqCode('ct-sp');
        $create = $this->apiPost('/api/inspection/specialties', [
            'code' => $code, 'officialNo' => "ON-{$code}", 'name' => "sp {$code}",
        ]);
        $create->assertOk();
        $sp = $create->json();
        $this->assertSame($code, $sp['code']);
        $this->assertTrue($sp['isOfficial'], 'isOfficial 缺省 true');
        $this->assertTrue($sp['enabled'], 'enabled 缺省 true');

        $update = $this->apiPut("/api/inspection/specialties/{$code}", ['name' => 'renamed']);
        $update->assertOk();
        $this->assertSame('renamed', $update->json('name'));

        $this->apiDelete("/api/inspection/specialties/{$code}")->assertNoContent();
    }

    #[Test]
    public function m06_f01_specialty_create_without_official_no_is_400(): void
    {
        // ct T11 实证 official_no NOT NULL
        $this->apiPost('/api/inspection/specialties', [
            'code' => $this->uniqCode('ct-sp'), 'name' => 'no officialNo',
        ])->assertBadRequest();
    }

    #[Test]
    public function m06_f01_specialty_delete_unknown_code_is_404(): void
    {
        $this->apiDelete('/api/inspection/specialties/no-such-sp')->assertNotFound();
    }

    // === objects ===

    #[Test]
    public function m06_f02_object_create_five_required_fields_then_list_filters_by_specialty(): void
    {
        $seed = $this->seedDictionary();
        $code = $this->uniqCode('ct-obj');

        $create = $this->apiPost('/api/inspection/objects', [
            'code' => $code, 'name' => "obj {$code}",
            'inspectionSpecialtyCode' => $seed['specialty']->code,
            'sourceProjectNo' => $code, 'sourceProjectName' => "proj {$code}",
        ]);
        $create->assertOk();
        $body = $create->json();
        $this->assertSame($seed['specialty']->code, $body['inspectionSpecialtyCode']);
        $this->assertFalse($body['isOptionalForQualification'], '缺省 false');

        $list = $this->apiGet('/api/inspection/objects?inspectionSpecialtyCode='.urlencode($seed['specialty']->code));
        $list->assertOk();
        // seedDictionary 已建 1 个同 specialty 的 object，加上本次新建共 2
        $this->assertSame(2, $list->json('total'));

        $this->apiDelete("/api/inspection/objects/{$code}")->assertNoContent();
    }

    #[Test]
    public function m06_f02_object_create_without_source_project_no_is_400(): void
    {
        $seed = $this->seedDictionary();

        $this->apiPost('/api/inspection/objects', [
            'code' => $this->uniqCode('ct-obj'), 'name' => 'x',
            'inspectionSpecialtyCode' => $seed['specialty']->code,
            'sourceProjectName' => 'proj',
        ])->assertBadRequest();
    }

    // === parameters ===

    #[Test]
    public function m06_f03_parameter_create_defaults_then_update_aliases_then_delete(): void
    {
        $code = $this->uniqCode('ct-prm');

        $create = $this->apiPost('/api/inspection/parameters', [
            'code' => $code, 'name' => "prm {$code}",
        ]);
        $create->assertOk();
        $body = $create->json();
        $this->assertSame("prm {$code}", $body['rawName']);
        $this->assertSame("prm {$code}", $body['canonicalName']);
        $this->assertSame([], $body['aliases']);
        $this->assertSame('official', $body['sourceType']);

        $update = $this->apiPut("/api/inspection/parameters/{$code}", [
            'aliases' => ['pH', '酸碱度'],
        ]);
        $update->assertOk();
        $this->assertSame(['pH', '酸碱度'], $update->json('aliases'));

        $this->apiDelete("/api/inspection/parameters/{$code}")->assertNoContent();
    }

    #[Test]
    public function m06_f03_parameter_list_filters_by_source_type(): void
    {
        $seed = $this->seedDictionary();

        $list = $this->apiGet('/api/inspection/parameters?sourceType=official');
        $list->assertOk();
        $items = $list->json('items');
        foreach ($items as $item) {
            $this->assertSame('official', $item['sourceType']);
        }
        $codes = array_column($items, 'code');
        $this->assertContains($seed['parameter']->code, $codes);
    }

    // === standards ===

    #[Test]
    public function m06_f04_standard_create_defaults_status_active_then_update_version(): void
    {
        $code = $this->uniqCode('ct-std');

        $create = $this->apiPost('/api/inspection/standards', [
            'code' => $code, 'name' => "std {$code}",
        ]);
        $create->assertOk();
        $this->assertSame('active', $create->json('status'));

        $update = $this->apiPut("/api/inspection/standards/{$code}", ['version' => '2024-1']);
        $update->assertOk();
        $this->assertSame('2024-1', $update->json('version'));

        $this->apiDelete("/api/inspection/standards/{$code}")->assertNoContent();
    }

    #[Test]
    public function m06_f04_standard_list_filters_by_status(): void
    {
        $seed = $this->seedDictionary();

        $active = $this->apiGet('/api/inspection/standards?status=active');
        $active->assertOk();
        $items = $active->json('items');
        foreach ($items as $item) {
            $this->assertSame('active', $item['status']);
        }
        $codes = array_column($items, 'code');
        $this->assertContains($seed['standard']->code, $codes);

        $superseded = $this->apiGet('/api/inspection/standards?status=superseded');
        $superseded->assertOk();
        $this->assertSame(0, $superseded->json('total'));
    }

    // === 4 类 junction ===

    #[Test]
    public function m06_specialty_object_link_unlink_idempotent_and_list_filter(): void
    {
        $seed = $this->seedDictionary();
        $body = ['inspectionSpecialtyCode' => $seed['specialty']->code,
            'inspectionObjectCode' => $seed['object']->code];

        $this->postJson('/api/inspection/links/specialty-object', $body, $this->authHeader())
            ->assertNoContent();

        $list = $this->apiGet('/api/inspection/links/specialty-object?inspectionSpecialtyCode='.urlencode($seed['specialty']->code));
        $list->assertOk();
        $this->assertSame(1, $list->json('total'));

        $this->deleteJson('/api/inspection/links/specialty-object', $body, $this->authHeader())
            ->assertNoContent();
        $this->deleteJson('/api/inspection/links/specialty-object', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m06_object_parameter_link_defaults_qualification_level_then_unlink_idempotent(): void
    {
        $seed = $this->seedDictionary();
        $body = ['inspectionObjectCode' => $seed['object']->code,
            'inspectionParameterCode' => $seed['parameter']->code];

        $this->postJson('/api/inspection/links/object-parameter', $body, $this->authHeader())
            ->assertNoContent();

        $list = $this->getJson('/api/inspection/links/object-parameter?'.http_build_query($body), $this->authHeader());
        $list->assertOk();
        $this->assertSame('QUALIFIED', $list->json('items.0.qualificationLevel'));

        $this->deleteJson('/api/inspection/links/object-parameter', $body, $this->authHeader())
            ->assertNoContent();
        $this->deleteJson('/api/inspection/links/object-parameter', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m06_object_standard_link_role_required_then_list_filters_by_role(): void
    {
        $seed = $this->seedDictionary();
        $body = ['inspectionObjectCode' => $seed['object']->code,
            'inspectionStandardCode' => $seed['standard']->code, 'role' => 'JUDGMENT'];

        $this->postJson('/api/inspection/links/object-standard', $body, $this->authHeader())
            ->assertNoContent();

        $this->postJson('/api/inspection/links/object-standard', [
            'inspectionObjectCode' => $seed['object']->code,
            'inspectionStandardCode' => $seed['standard']->code,
        ], $this->authHeader())->assertBadRequest();

        $judgment = $this->apiGet('/api/inspection/links/object-standard?'.http_build_query([
            'inspectionObjectCode' => $seed['object']->code, 'role' => 'JUDGMENT',
        ]));
        $judgment->assertOk();
        $this->assertSame(1, $judgment->json('total'));

        $testing = $this->apiGet('/api/inspection/links/object-standard?'.http_build_query([
            'inspectionObjectCode' => $seed['object']->code, 'role' => 'TESTING',
        ]));
        $testing->assertOk();
        $this->assertSame(0, $testing->json('total'));

        $this->deleteJson('/api/inspection/links/object-standard', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m06_standard_parameter_link_unlink_idempotent(): void
    {
        $seed = $this->seedDictionary();
        $body = ['inspectionStandardCode' => $seed['standard']->code,
            'inspectionParameterCode' => $seed['parameter']->code];

        $this->postJson('/api/inspection/links/standard-parameter', $body, $this->authHeader())
            ->assertNoContent();

        $list = $this->apiGet('/api/inspection/links/standard-parameter?inspectionStandardCode='.urlencode($seed['standard']->code));
        $list->assertOk();
        $this->assertSame(1, $list->json('total'));

        $this->deleteJson('/api/inspection/links/standard-parameter', $body, $this->authHeader())
            ->assertNoContent();
        $this->deleteJson('/api/inspection/links/standard-parameter', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m06_without_token_is_401(): void
    {
        $this->getJson('/api/inspection/specialties')->assertUnauthorized();
    }
}
