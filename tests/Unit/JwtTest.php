<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\Jwt\TokenExpired;
use App\Exceptions\Jwt\TokenInvalid;
use App\Services\Auth\JwtService;
use Illuminate\Support\Env;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\TestJwt;
use Tests\TestCase;

// JwtService（lab 形态）契约（Task 5-13 鉴权消费面）：HS256 手写实现，零 composer
// 依赖。lab claims 形状（rails LabAuth::TokenService 镜像，与 saas 的有意差异）：
// - access：{sub, iat, exp, typ='access', iss, tenant_id?}——**无 aud**；TTL=JWT_TTL_SECONDS
// - refresh：{sub, saas_refresh_token, typ='refresh', iss, iat, exp}——TTL=JWT_REFRESH_TTL_SECONDS
// fail-fast：key 缺失或 <32B 构造即 throw（suite-hard-rules §1，禁 env 默认值兜底）。
final class JwtTest extends TestCase
{
    // —— 签发/验签往返（正常面） ——

    #[Test]
    public function access_roundtrip_carries_lab_claims_and_ttl(): void
    {
        $jwt = new JwtService;
        $payload = $jwt->verify($jwt->issueAccess('USER-A', 'TENANT-001'));

        $this->assertSame('USER-A', $payload['sub']);
        $this->assertSame('TENANT-001', $payload['tenant_id']);
        $this->assertSame('access', $payload['typ']);
        $this->assertSame('lab-management-system', $payload['iss']); // .env.test JWT_ISSUER 家族值
        $this->assertSame(3600, $payload['exp'] - $payload['iat']); // .env.test JWT_TTL_SECONDS
        $this->assertArrayNotHasKey('aud', $payload); // lab 有意无 aud（与 saas 的契约差异）
    }

    #[Test]
    public function access_without_tenant_omits_tenant_claim(): void
    {
        // issue_access(user, nil) 镜像：tenant_id 不出 claim（而不是空串——claim 空值
        // 由 guard 侧 defaultTenant 回退兜住，见 GuardShapeTest）。
        $payload = (new JwtService)->verify((new JwtService)->issueAccess('USER-A', null));

        $this->assertSame('USER-A', $payload['sub']);
        $this->assertArrayNotHasKey('tenant_id', $payload);
    }

    #[Test]
    public function refresh_roundtrip_carries_saas_refresh_token_claim(): void
    {
        $jwt = new JwtService;
        $payload = $jwt->verify($jwt->issueRefresh('USER-A', 'saas-rt-1'), 'refresh');

        $this->assertSame('USER-A', $payload['sub']);
        $this->assertSame('saas-rt-1', $payload['saas_refresh_token']);
        $this->assertSame('refresh', $payload['typ']);
        $this->assertSame('lab-management-system', $payload['iss']);
        $this->assertSame(604800, $payload['exp'] - $payload['iat']); // .env.test JWT_REFRESH_TTL_SECONDS
    }

    #[Test]
    public function refresh_token_rejected_when_verified_as_access(): void
    {
        // rails verify!(typ: TYP_ACCESS) 的 "unexpected typ" 语义：typ 不符即拒。
        $this->expectException(TokenInvalid::class);
        $this->expectExceptionMessage('unexpected typ: refresh');

        (new JwtService)->verify((new JwtService)->issueRefresh('USER-A', 'saas-rt-1'));
    }

    // —— 无效面（expired / 签名 / alg 混淆 / 形状） ——

    #[Test]
    public function expired_access_token_is_rejected(): void
    {
        $this->expectException(TokenExpired::class);

        (new JwtService)->verify(TestJwt::expired());
    }

    #[Test]
    public function expired_refresh_token_is_rejected(): void
    {
        $this->expectException(TokenExpired::class);

        (new JwtService)->verify(TestJwt::expiredRefresh(), 'refresh');
    }

    #[Test]
    public function wrong_signature_is_rejected(): void
    {
        $this->expectException(TokenInvalid::class);
        $this->expectExceptionMessage('signature mismatch');

        $token = TestJwt::sign(
            ['sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access', 'iat' => time(), 'exp' => time() + 3600],
            'another-key-32-bytes-minimum-length!!'
        );
        (new JwtService)->verify($token);
    }

    #[Test]
    public function alg_none_confusion_is_rejected(): void
    {
        $this->expectException(TokenInvalid::class);

        (new JwtService)->verify(TestJwt::algNone());
    }

    #[Test]
    public function alg_hs512_confusion_is_rejected(): void
    {
        // alg ≠ HS256 即拒（header 混淆攻击面），即便签名本身按同 key 算得对。
        $this->expectException(TokenInvalid::class);

        $token = TestJwt::sign(
            ['sub' => 'USER-A', 'typ' => 'access', 'iat' => time(), 'exp' => time() + 3600],
            null,
            'HS512'
        );
        (new JwtService)->verify($token);
    }

    #[Test]
    public function malformed_token_segments_are_rejected(): void
    {
        $this->expectException(TokenInvalid::class);

        (new JwtService)->verify('not-a-jwt');
    }

    // —— iss 严格相等（rails verify_iss: true 镜像；lab 无 verify_aud） ——

    #[Test]
    public function wrong_issuer_is_rejected(): void
    {
        $this->expectException(TokenInvalid::class);

        (new JwtService)->verify(TestJwt::sign([
            'sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access',
            'iat' => time(), 'exp' => time() + 3600, 'iss' => 'other-issuer',
        ]));
    }

    #[Test]
    public function missing_issuer_claim_is_rejected(): void
    {
        // 缺身份 claim 禁静默放行（ADR-0019）。
        $this->expectException(TokenInvalid::class);

        (new JwtService)->verify(TestJwt::sign([
            'sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access',
            'iat' => time(), 'exp' => time() + 3600, 'iss' => null,
        ]));
    }

    #[Test]
    public function missing_exp_claim_is_rejected(): void
    {
        $this->expectException(TokenInvalid::class);
        $this->expectExceptionMessage('missing exp claim');

        (new JwtService)->verify(TestJwt::sign([
            'sub' => 'USER-A', 'typ' => 'access', 'iss' => 'lab-management-system', 'iat' => time(),
        ]));
    }

    // —— fail-fast：env 缺失/非法构造即 throw ——
    #[Test]
    public function key_shorter_than_32_bytes_throws_at_construction(): void
    {
        // rails raise ArgumentError 'JWT_SIGNING_KEY must be >=32 bytes' 镜像。
        $this->withEnv('JWT_SIGNING_KEY', 'short-key', function (): void {
            try {
                new JwtService;
                self::fail('JWT_SIGNING_KEY <32B 必须 throw（fail-fast——suite-hard-rules §1）');
            } catch (AssertionFailedError $e) {
                throw $e; // fail() 是 RuntimeException 子类，先接住重抛防被下行 catch 吞成假绿
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('32', $e->getMessage());
            }
        });
    }

    #[Test]
    public function missing_signing_key_throws_fail_fast(): void
    {
        $this->withoutEnv('JWT_SIGNING_KEY', function (): void {
            try {
                new JwtService;
                self::fail('缺 JWT_SIGNING_KEY 必须 throw（fail-fast，禁 env 默认值兜底——suite-hard-rules §1）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_SIGNING_KEY', $e->getMessage());
            }
        });
    }

    #[Test]
    public function missing_issuer_env_throws_fail_fast(): void
    {
        $this->withoutEnv('JWT_ISSUER', function (): void {
            try {
                new JwtService;
                self::fail('缺 JWT_ISSUER 必须 throw（fail-fast，禁 env 默认值兜底——suite-hard-rules §1）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_ISSUER', $e->getMessage());
            }
        });
    }

    #[Test]
    public function missing_ttl_env_throws_fail_fast(): void
    {
        $this->withoutEnv('JWT_TTL_SECONDS', function (): void {
            try {
                new JwtService;
                self::fail('缺 JWT_TTL_SECONDS 必须 throw（fail-fast——suite-hard-rules §1）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_TTL_SECONDS', $e->getMessage());
            }
        });
    }

    #[Test]
    public function missing_refresh_ttl_env_throws_fail_fast(): void
    {
        $this->withoutEnv('JWT_REFRESH_TTL_SECONDS', function (): void {
            try {
                new JwtService;
                self::fail('缺 JWT_REFRESH_TTL_SECONDS 必须 throw（fail-fast——suite-hard-rules §1）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_REFRESH_TTL_SECONDS', $e->getMessage());
            }
        });
    }

    #[Test]
    public function ttl_with_trailing_newline_throws_fail_fast(): void
    {
        // regex $ 无 D 修饰符会放行尾随 \n（"3600\n" 不是合法正整数，D 严格语义）。
        $this->withEnv('JWT_TTL_SECONDS', "3600\n", function (): void {
            try {
                new JwtService;
                self::fail('JWT_TTL_SECONDS 带尾随换行必须 throw（\\z 严格语义）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_TTL_SECONDS', $e->getMessage());
            }
        });
    }

    #[Test]
    public function non_numeric_ttl_throws_fail_fast(): void
    {
        $this->withEnv('JWT_TTL_SECONDS', 'abc', function (): void {
            try {
                new JwtService;
                self::fail('JWT_TTL_SECONDS 非正整数必须 throw（fail-fast）');
            } catch (AssertionFailedError $e) {
                throw $e;
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('JWT_TTL_SECONDS', $e->getMessage());
            }
        });
    }

    // —— env 操盘助手：Laravel Env repository 直读超全局/live getenv，三处同撤同还 ——

    private function withoutEnv(string $key, callable $fn): void
    {
        $saved = Env::get($key);
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
        try {
            $fn();
        } finally {
            $this->restoreEnv($key, $saved);
        }
    }

    private function withEnv(string $key, string $value, callable $fn): void
    {
        $saved = Env::get($key);
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        try {
            $fn();
        } finally {
            $this->restoreEnv($key, $saved);
        }
    }

    private function restoreEnv(string $key, mixed $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
