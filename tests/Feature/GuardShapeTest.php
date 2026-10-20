<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AccessDenied;
use App\Exceptions\InvalidCredentials;
use App\Exceptions\InvalidGrant;
use App\Exceptions\Jwt\TokenInvalid;
use App\Exceptions\MenusUnavailable;
use App\Exceptions\SaasIdpUnavailable;
use App\Exceptions\UpstreamUnavailable;
use App\Http\Middleware\JwtGuard;
use App\Models\Contract;
use App\Services\Auth\JwtService;
use App\Services\Auth\UserDirectory;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Support\TestJwt;
use Tests\TestCase;

// JwtGuard + 基座错误形状（Task 5-13 消费契约，rails application_controller rescue_from
// 表 + jwt_guard concern 逐行镜像）。直签 token 直打中间件，不依赖登录链（Task 5 接入）。
final class GuardShapeTest extends TestCase
{
    // ---- JwtGuard：401 形状（缺头/坏头/过期/坏签名/alg 混淆/iss 不符） ----

    #[Test]
    public function missing_authorization_header_is_401_with_authenticate_challenge(): void
    {
        $response = $this->runGuard($this->requestWithoutToken());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            ['code' => 'INVALID_CREDENTIALS', 'message' => 'missing bearer token'],
            $this->bodyOf($response)
        );
        $this->assertSame('Bearer realm="api"', $response->headers->get('WWW-Authenticate'));
    }

    #[Test]
    public function header_without_bearer_prefix_is_401(): void
    {
        $request = $this->requestWithoutToken();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $response = $this->runGuard($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('missing bearer token', $this->bodyOf($response)['message']);
    }

    #[Test]
    public function expired_token_is_401_token_expired(): void
    {
        $response = $this->runGuard($this->requestWithToken(TestJwt::expired()));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            ['code' => 'INVALID_CREDENTIALS', 'message' => 'token expired'],
            $this->bodyOf($response)
        );
        $this->assertSame('Bearer realm="api"', $response->headers->get('WWW-Authenticate'));
    }

    #[Test]
    public function wrong_signature_is_401_invalid_token(): void
    {
        $token = TestJwt::sign(
            ['sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access', 'iat' => time(), 'exp' => time() + 3600],
            'another-key-32-bytes-minimum-length!!'
        );

        $response = $this->runGuard($this->requestWithToken($token));

        $this->assertSame(401, $response->getStatusCode());
        $body = $this->bodyOf($response);
        $this->assertSame('INVALID_CREDENTIALS', $body['code']);
        $this->assertStringStartsWith('invalid token: ', $body['message']);
    }

    #[Test]
    public function alg_none_confusion_is_401(): void
    {
        $response = $this->runGuard($this->requestWithToken(TestJwt::algNone()));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('invalid token: ', $this->bodyOf($response)['message']);
    }

    #[Test]
    public function wrong_issuer_token_is_401(): void
    {
        $token = TestJwt::sign([
            'sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access',
            'iat' => time(), 'exp' => time() + 3600, 'iss' => 'other-issuer',
        ]);

        $response = $this->runGuard($this->requestWithToken($token));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('INVALID_CREDENTIALS', $this->bodyOf($response)['code']);
    }

    #[Test]
    public function refresh_typ_token_is_rejected_by_guard(): void
    {
        // guard 只放行 typ=access：refresh token 打保护面 → 401（rails verify! typ 镜像）。
        $token = (new JwtService)->issueRefresh('USER-A', 'saas-rt-1');

        $response = $this->runGuard($this->requestWithToken($token));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('unexpected typ: refresh', (string) $this->bodyOf($response)['message']);
    }

    // ---- JwtGuard：身份缝（attributes）+ tenant_id 缺省回退 ----

    #[Test]
    public function valid_token_passes_through_and_exposes_identity_attributes(): void
    {
        // Task 5-13 消费约定：guard 把身份合并进 request attributes，控制器经
        // $request->attributes->get('currentUserId' / 'currentTenantId') 读取；查询必须
        // 用它过滤（where tenant_id = currentTenantId），禁全表泄露；身份字段缺失必须
        // 401/403，禁 demo 字面量兜底（ADR-0019 / suite-hard-rules §3）。
        $token = (new JwtService)->issueAccess('USER-A', 'TENANT-002');
        $seen = null;

        $response = $this->runGuard($this->requestWithToken($token), function (Request $r) use (&$seen): Response {
            $seen = [
                'userId' => $r->attributes->get('currentUserId'),
                'tenantId' => $r->attributes->get('currentTenantId'),
            ];

            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['userId' => 'USER-A', 'tenantId' => 'TENANT-002'], $seen);
    }

    #[Test]
    public function missing_tenant_claim_falls_back_to_default_tenant(): void
    {
        // claim tenant_id 缺省回退 UserDirectory::defaultTenant()（TENANT-001 硬编码，
        // 禁 LAB_SAAS_DEFAULT_TENANT_ID env 兜底——业务租户域与 saas 服务账号域分离，
        // 2026-09-29 live 实锤）。access token 无 tenant claim 是合法形状（lab claims
        // tenant_id?），不能 401。
        $token = (new JwtService)->issueAccess('USER-A', null);
        $seen = null;

        $response = $this->runGuard($this->requestWithToken($token), function (Request $r) use (&$seen): Response {
            $seen = $r->attributes->get('currentTenantId');

            return new Response('ok', 200);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('TENANT-001', $seen);
        $this->assertSame('TENANT-001', (new UserDirectory)->defaultTenant()['tenant_id']);
    }

    #[Test]
    public function blank_tenant_claim_also_falls_back_to_default_tenant(): void
    {
        $token = TestJwt::sign([
            'sub' => 'USER-A', 'tenant_id' => '', 'typ' => 'access', 'iat' => time(), 'exp' => time() + 3600,
        ]);
        $seen = null;

        $this->runGuard($this->requestWithToken($token), function (Request $r) use (&$seen): Response {
            $seen = $r->attributes->get('currentTenantId');

            return new Response('ok', 200);
        });

        $this->assertSame('TENANT-001', $seen);
    }

    // ---- 基座错误映射（bootstrap withExceptions，rails rescue_from 表逐行） ----

    #[Test]
    public function invalid_credentials_renders_401_shape(): void
    {
        $response = $this->renderException(new InvalidCredentials('invalid credentials'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(
            ['code' => 'INVALID_CREDENTIALS', 'message' => 'invalid credentials'],
            $this->bodyOf($response)
        );
    }

    #[Test]
    public function access_denied_renders_403_shape(): void
    {
        $response = $this->renderException(new AccessDenied('forbidden role'));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['code' => 'FORBIDDEN', 'message' => 'forbidden role'], $this->bodyOf($response));
    }

    #[Test]
    public function menus_unavailable_renders_503_shape(): void
    {
        $response = $this->renderException(new MenusUnavailable('saas menus down'));

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['code' => 'MENUS_UNAVAILABLE', 'message' => 'saas menus down'], $this->bodyOf($response));
    }

    #[Test]
    public function saas_idp_unavailable_renders_502_saas_unavailable(): void
    {
        // REQ-2026-023：登录路径五仓统一码 SAAS_UNAVAILABLE。
        $response = $this->renderException(new SaasIdpUnavailable('idp unreachable'));

        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame(['code' => 'SAAS_UNAVAILABLE', 'message' => 'idp unreachable'], $this->bodyOf($response));
    }

    #[Test]
    public function invalid_grant_renders_400_shape(): void
    {
        // saas InvalidGrant → 400 INVALID_GRANT（code 重放/过期）。
        $response = $this->renderException(new InvalidGrant('invalid_grant: code expired'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['code' => 'INVALID_GRANT', 'message' => 'invalid_grant: code expired'], $this->bodyOf($response));
    }

    #[Test]
    public function upstream_unavailable_renders_502_saas_upstream_error(): void
    {
        // sso 路径 UpstreamUnavailable → 502 SAAS_UPSTREAM_ERROR（与登录路径分流）。
        $response = $this->renderException(new UpstreamUnavailable('upstream 5xx'));

        $this->assertSame(502, $response->getStatusCode());
        $this->assertSame(['code' => 'SAAS_UPSTREAM_ERROR', 'message' => 'upstream 5xx'], $this->bodyOf($response));
    }

    #[Test]
    public function token_invalid_thrown_outside_guard_maps_to_401(): void
    {
        // decode_bearer!（oauth 链手动 decode）等非中间件路径走 handler：→ 401。
        $response = $this->renderException(new TokenInvalid('signature mismatch'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['code' => 'INVALID_CREDENTIALS', 'message' => 'signature mismatch'], $this->bodyOf($response));
    }

    #[Test]
    public function argument_error_renders_400_shape(): void
    {
        $response = $this->renderException(new \InvalidArgumentException('pageSize must be >= 1'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['code' => 'BAD_REQUEST', 'message' => 'pageSize must be >= 1'], $this->bodyOf($response));
    }

    #[Test]
    public function model_not_found_with_malformed_uuid_id_is_400(): void
    {
        // rails UUID 甄别镜像：路径段声称 uuid 但格式非法 → 400（IAE 语义）。
        // lab 路由参数实态（routes/api.php）：{id} 是唯一 uuid 形参；{code}/
        // {inspectionObjectCode}/{inspectionParameterCode}/{judgmentStandardCode} 是
        // 业务串码（rails bad_uuid_param 不扫 :code——码 miss 走 404，见下例）。
        $request = $this->requestWithRouteParams(['id' => 'not-a-uuid']);

        $response = $this->renderException($this->notFound(['not-a-uuid']), $request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['code' => 'BAD_REQUEST', 'message' => 'malformed uuid: not-a-uuid'], $this->bodyOf($response));
    }

    #[Test]
    public function model_not_found_with_valid_uuid_is_404(): void
    {
        // 合法格式但查无 → 404 NOT_FOUND。
        $uuid = '00000000-0000-0000-0000-000000000000';
        $request = $this->requestWithRouteParams(['id' => $uuid]);

        $response = $this->renderException($this->notFound([$uuid]), $request);

        $this->assertSame(404, $response->getStatusCode());
        $body = $this->bodyOf($response);
        $this->assertSame('NOT_FOUND', $body['code']);
        $this->assertStringContainsString('No query results', $body['message']);
    }

    #[Test]
    public function model_not_found_without_uuid_param_is_plain_404(): void
    {
        // rails :code 不在 bad_uuid_param 扫描表——业务码 miss 是 404，不是 400。
        $request = $this->requestWithRouteParams(['code' => 'IO-999']);

        $response = $this->renderException($this->notFound(['IO-999']), $request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('NOT_FOUND', $this->bodyOf($response)['code']);
    }

    #[Test]
    public function uuid_param_with_trailing_newline_is_400_not_404(): void
    {
        // UUID_RE \z 严格语义：尾随 \n 不算合法 uuid（PHP $ 无 D 修饰符会匹配尾随换行
        // 前位置——漏判把坏 uuid 走 404「查无」口径）。
        $uuid = "00000000-0000-0000-0000-000000000000\n";
        $request = $this->requestWithRouteParams(['id' => $uuid]);

        $response = $this->renderException($this->notFound([$uuid]), $request);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('BAD_REQUEST', $this->bodyOf($response)['code']);
    }

    #[Test]
    public function integrity_violation_renders_400_constraint_violation(): void
    {
        // 唯一/外键/非空冲突 → 400 "constraint violation: …"（rails RecordNotUnique 同组镜像）。
        $e = new QueryException(
            'pgsql',
            'insert into contracts ...',
            [],
            new PDOException('SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value', 23505)
        );

        $response = $this->renderException($e);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->bodyOf($response);
        $this->assertSame('BAD_REQUEST', $body['code']);
        $this->assertStringStartsWith('constraint violation: ', $body['message']);
    }

    #[Test]
    public function malformed_body_renders_400_fixed_message(): void
    {
        // ParameterMissing / JSON.parse 失败镜像（HttpMessageNotReadable → 400）。
        $response = $this->renderException(new BadRequestHttpException('Invalid JSON body.'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['code' => 'BAD_REQUEST', 'message' => 'malformed request body'], $this->bodyOf($response));
    }

    #[Test]
    public function unmapped_exception_falls_through_to_default_handling(): void
    {
        // 映射表外的异常（如真 SQL 错误 42P01）不吞——走框架默认 500。
        $e = new QueryException(
            'pgsql',
            'select * from missing_table',
            [],
            new PDOException('SQLSTATE[42P01]: Undefined table', 42601)
        );

        $response = $this->renderException($e);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ---- 助手 ----

    private function runGuard(Request $request, ?callable $next = null): Response
    {
        $guard = new JwtGuard(new JwtService);
        $next ??= fn (): Response => new Response('ok', 200);

        return $guard->handle($request, $next);
    }

    private function requestWithoutToken(): Request
    {
        return Request::create('/api/auth/me', 'GET');
    }

    private function requestWithToken(string $token): Request
    {
        $request = $this->requestWithoutToken();
        $request->headers->set('Authorization', 'Bearer '.$token);

        return $request;
    }

    private function requestWithRouteParams(array $params): Request
    {
        $request = Request::create('/api/contracts/x', 'GET');
        $route = new Route('GET', '/api/contracts/{id}', []);
        $route->parameters = $params;
        $request->setRouteResolver(fn (): Route => $route);

        return $request;
    }

    private function notFound(array $ids): ModelNotFoundException
    {
        $e = new ModelNotFoundException;
        $e->setModel(Contract::class, $ids);

        return $e;
    }

    private function renderException(\Throwable $e, ?Request $request = null): Response
    {
        /** @var ExceptionHandler $handler */
        $handler = $this->app->make(ExceptionHandler::class);

        return $handler->render($request ?? $this->requestWithoutToken(), $e);
    }

    // handler 命中映射表回 JsonResponse、落空回框架默认页——按 content 解码统一取 body。
    private function bodyOf(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
