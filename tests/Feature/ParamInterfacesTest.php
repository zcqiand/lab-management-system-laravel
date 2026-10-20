<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\ParamInterface\ParamInterfaceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\LabSupport;
use Tests\TestCase;

// M10 参数接口（平台级字典 CRUD :code + junction link）集成测试
// （镜像 lab rails test/integration/param_interfaces_test.rb 全部 9 用例，不加戏）。
// 对照 ct：param-interfaces.test.ts / param-interfaces-write.test.ts；
// 参照语义：lab-springboot ParamInterfaceService/Mapper + InspectionJunctionService。
final class ParamInterfacesTest extends TestCase
{
    use DatabaseTransactions;
    use LabSupport;

    #[Test]
    public function m10_list_envelope_page1_pagesize_total(): void
    {
        $body = $this->apiGet('/api/param-interfaces')->assertOk()->json();
        foreach (['page', 'pageSize', 'total', 'items'] as $key) {
            $this->assertArrayHasKey($key, $body, "缺 {$key}");
        }
        $this->assertSame(1, $body['page']);
        $this->assertSame($body['total'], $body['pageSize']);
        $this->assertIsArray($body['items']);
    }

    #[Test]
    public function m10_get_unknown_code_is_404(): void
    {
        $response = $this->apiGet('/api/param-interfaces/no-such-pi');
        $response->assertNotFound();
        $this->assertSame('NOT_FOUND', $response->json('code'));
    }

    #[Test]
    public function m10_create_echoes_config_and_default_sort_order(): void
    {
        $code = $this->uniqCode('ct-pi');

        $create = $this->apiPost('/api/param-interfaces', [
            'code' => $code, 'name' => "pi {$code}", 'componentPath' => 'cards/default',
            'config' => ['layout' => 'grid'],
        ]);
        $create->assertOk();
        $body = $create->json();
        $this->assertSame($code, $body['code']);
        $this->assertSame('cards/default', $body['componentPath']);
        $this->assertSame(['layout' => 'grid'], $body['config']);
        $this->assertSame(0, $body['sortOrder']);
    }

    #[Test]
    public function m10_create_without_component_path_is_400(): void
    {
        // ct 实证：宽松 mock 曾掩盖
        $response = $this->apiPost('/api/param-interfaces', [
            'code' => $this->uniqCode('ct-pi'), 'name' => 'no path',
        ]);
        $response->assertBadRequest();
        $this->assertSame('BAD_REQUEST', $response->json('code'));
    }

    #[Test]
    public function m10_update_renames(): void
    {
        $code = $this->createPi($this->uniqCode('ct-pi'));

        $update = $this->apiPut("/api/param-interfaces/{$code}", ['name' => 'renamed']);
        $update->assertOk();
        $this->assertSame('renamed', $update->json('name'));
    }

    #[Test]
    public function m10_delete_is_204_then_repeat_is_404(): void
    {
        $code = $this->createPi($this->uniqCode('ct-pi'));

        $this->apiDelete("/api/param-interfaces/{$code}")->assertNoContent();
        $this->apiDelete("/api/param-interfaces/{$code}")->assertNotFound();
    }

    #[Test]
    public function m10_link_204_unlink_idempotent_links_list_envelope(): void
    {
        $seed = $this->seedDictionary();
        $interfaceCode = $this->uniqCode('ct-pi');
        $this->createPi($interfaceCode);
        $body = ['inspectionParameterCode' => $seed['parameter']->code,
            'paramInterfaceCode' => $interfaceCode, 'config' => ['mode' => 'input']];

        $this->postJson('/api/param-interfaces/links', $body, $this->authHeader())
            ->assertNoContent();

        $list = $this->apiGet('/api/param-interfaces/links?'.http_build_query([
            'inspectionParameterCode' => $body['inspectionParameterCode'],
        ]));
        $list->assertOk();
        $links = $list->json();
        $this->assertSame(1, $links['total']);
        $this->assertSame(1, $links['page']);
        $this->assertSame(['mode' => 'input'], $links['items'][0]['config']);

        $this->deleteJson('/api/param-interfaces/links', $body, $this->authHeader())
            ->assertNoContent();

        // 幂等：重复 unlink 未命中同样 204
        $this->deleteJson('/api/param-interfaces/links', $body, $this->authHeader())
            ->assertNoContent();
    }

    #[Test]
    public function m10_link_missing_param_interface_code_is_400(): void
    {
        $seed = $this->seedDictionary();

        $this->postJson('/api/param-interfaces/links', [
            'inspectionParameterCode' => $seed['parameter']->code,
        ], $this->authHeader())->assertBadRequest();
    }

    #[Test]
    public function m10_without_token_is_401(): void
    {
        $this->getJson('/api/param-interfaces')->assertUnauthorized();
    }

    // === private（rails create_pi helper 同位） ===

    private function createPi(string $code): string
    {
        (new ParamInterfaceService)->create([
            'code' => $code, 'name' => "pi {$code}", 'componentPath' => 'cards/default',
        ]);

        return $code;
    }
}
