<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\Jwt\TokenExpired;
use App\Exceptions\Jwt\TokenInvalid;
use Illuminate\Support\Env;
use RuntimeException;

// lab 自签 JWT（HS256）——rails LabAuth::TokenService 的 Laravel 镜像，零 composer
// 依赖（hash_hmac + base64url + json_encode 手写）。与 saas 家族 verifier 的有意差异：
// - claims **无 aud**（lab 契约 token 不带 audience）
// - access：{sub, iat, exp, typ='access', iss, tenant_id?}；TTL=JWT_TTL_SECONDS
// - refresh：{sub, saas_refresh_token, typ='refresh', iss, iat, exp}；TTL=JWT_REFRESH_TTL_SECONDS
// fail-fast：key 缺失或 <32B、issuer 缺失、TTL 缺失/非正整数——构造即 throw
// （suite-hard-rules §1，禁 env 默认值兜底）。
final class JwtService
{
    public const TYP_ACCESS = 'access';

    public const TYP_REFRESH = 'refresh';

    private const ALG = 'HS256';

    private string $signingKey;

    private string $issuer;

    private int $accessTtl;

    private int $refreshTtl;

    public function __construct()
    {
        $key = Env::get('JWT_SIGNING_KEY');
        if (! is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('JWT_SIGNING_KEY 缺失或 <32 bytes（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }
        $issuer = Env::get('JWT_ISSUER');
        if (! is_string($issuer) || $issuer === '') {
            throw new RuntimeException('JWT_ISSUER 缺失（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }
        $accessTtl = Env::get('JWT_TTL_SECONDS');
        $this->assertTtl('JWT_TTL_SECONDS', $accessTtl);
        $refreshTtl = Env::get('JWT_REFRESH_TTL_SECONDS');
        $this->assertTtl('JWT_REFRESH_TTL_SECONDS', $refreshTtl);

        $this->signingKey = $key;
        $this->issuer = $issuer;
        $this->accessTtl = (int) $accessTtl;
        $this->refreshTtl = (int) $refreshTtl;
    }

    public function accessTtl(): int
    {
        return $this->accessTtl;
    }

    public function refreshTtl(): int
    {
        return $this->refreshTtl;
    }

    // rails issue_access 镜像：tenant_id 缺席不出 claim（不是空串——claim 空值由
    // JwtGuard 侧 defaultTenant 回退兜住）。
    public function issueAccess(string $userId, ?string $tenantId = null): string
    {
        $now = time();
        $claims = ['sub' => $userId, 'iat' => $now, 'exp' => $now + $this->accessTtl,
            'typ' => self::TYP_ACCESS, 'iss' => $this->issuer];
        if (is_string($tenantId) && $tenantId !== '') {
            $claims['tenant_id'] = $tenantId;
        }

        return $this->encode($claims);
    }

    // rails issue_refresh 镜像：内嵌 saas refresh token（rotate-once reload 链用）。
    public function issueRefresh(string $userId, string $saasRefreshToken): string
    {
        $now = time();

        return $this->encode(['sub' => $userId, 'saas_refresh_token' => $saasRefreshToken,
            'iat' => $now, 'exp' => $now + $this->refreshTtl,
            'typ' => self::TYP_REFRESH, 'iss' => $this->issuer]);
    }

    /**
     * 验签 + exp/iss/typ 校验（rails JWT.decode verify_expiration/verify_iss +
     * "unexpected typ" 镜像；lab 无 verify_aud）。header alg ≠ HS256 即拒（混淆攻击面）。
     * 无效抛 TokenExpired / TokenInvalid，由调用方（JwtGuard / 基座映射）统一转 401。
     *
     * @return array<string, mixed>
     */
    public function verify(string $token, string $typ = self::TYP_ACCESS): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new TokenInvalid('wrong segment count');
        }
        [$head, $body, $sig] = $parts;

        $headerJson = $this->unb64($head);
        $header = is_string($headerJson) ? json_decode($headerJson, true) : null;
        if (! is_array($header) || ($header['alg'] ?? null) !== self::ALG) {
            throw new TokenInvalid('alg must be HS256');
        }

        $expected = hash_hmac('sha256', $head.'.'.$body, $this->signingKey, true);
        $actual = $this->unb64($sig);
        if (! is_string($actual) || ! hash_equals($expected, $actual)) {
            throw new TokenInvalid('signature mismatch');
        }

        $bodyJson = $this->unb64($body);
        $payload = is_string($bodyJson) ? json_decode($bodyJson, true) : null;
        if (! is_array($payload)) {
            throw new TokenInvalid('payload is not a JSON object');
        }
        if (! isset($payload['exp']) || ! is_int($payload['exp'])) {
            throw new TokenInvalid('missing exp claim');
        }
        if (time() >= $payload['exp']) {
            throw new TokenExpired('token expired');
        }

        // verify_iss 镜像：iss 严格相等（ruby-jwt InvalidIssuerError 同语义）。
        if (($payload['iss'] ?? null) !== $this->issuer) {
            throw new TokenInvalid('invalid issuer');
        }

        if (($payload['typ'] ?? null) !== $typ) {
            throw new TokenInvalid('unexpected typ: '.(is_string($payload['typ'] ?? null) ? $payload['typ'] : var_export($payload['typ'] ?? null, true)));
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function encode(array $claims): string
    {
        $head = $this->b64((string) json_encode(['alg' => self::ALG, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $body = $this->b64((string) json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $head.'.'.$body.'.'.$this->b64(hash_hmac('sha256', $head.'.'.$body, $this->signingKey, true));
    }

    private function assertTtl(string $key, mixed $value): void
    {
        // D 修饰符与 UUID_RE 同族：禁 $ 匹配尾随换行，"3600\n" 不放行。
        if (! is_string($value) || preg_match('/^\d+$/D', $value) !== 1) {
            throw new RuntimeException($key.' 缺失或非正整数（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function unb64(string $segment): string|false
    {
        $remainder = strlen($segment) % 4;
        if ($remainder > 0) {
            $segment .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($segment, '-_', '+/'), true);
    }
}
