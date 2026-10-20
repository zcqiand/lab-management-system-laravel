<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Env;

// 测试链 JWT 手工签发助手：JwtService 只产好 token，坏形状（坏签名/alg 混淆/过期/
// typ 错）由本助手手工构造。与 app/Services/Auth/JwtService 的 wire 形状一致
// （header.payload.signature，base64url 无 padding）。
// lab 差异（对 saas 同名件）：**无 aud claim**（lab 契约 token 不带 audience，与
// saas 家族 verifier 的有意差异），typ 由调用方逐 claim 显式给。
final class TestJwt
{
    /**
     * 手工签 HS256（或其他 alg 供混淆用例）。默认并入 iss 家族值；覆盖键优先；
     * 'iss' => null 即构造「缺 claim」形状。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload, ?string $key = null, string $alg = 'HS256'): string
    {
        $key ??= (string) Env::get('JWT_SIGNING_KEY');
        $payload = array_merge([
            'iss' => (string) Env::get('JWT_ISSUER'),
        ], $payload);
        $head = self::b64((string) json_encode(['alg' => $alg, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $body = self::b64((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig = self::b64(hash_hmac('sha256', $head.'.'.$body, $key, true));

        return $head.'.'.$body.'.'.$sig;
    }

    // lab access payload 形状：{sub, tenant_id?, typ='access', iss, iat, exp}；过期 1 小时前签发。
    public static function expired(): string
    {
        return self::sign([
            'sub' => 'USER-A', 'tenant_id' => 'TENANT-001', 'typ' => 'access',
            'iat' => time() - 7200, 'exp' => time() - 3600,
        ]);
    }

    // lab refresh payload 形状：{sub, saas_refresh_token, typ='refresh', iss, iat, exp}。
    public static function expiredRefresh(string $saasRefreshToken = 'saas-rt-1'): string
    {
        return self::sign([
            'sub' => 'USER-A', 'saas_refresh_token' => $saasRefreshToken, 'typ' => 'refresh',
            'iat' => time() - 2 * (int) Env::get('JWT_REFRESH_TTL_SECONDS'), 'exp' => time() - (int) Env::get('JWT_REFRESH_TTL_SECONDS'),
        ]);
    }

    // alg=none 混淆形状：header 声明 none、签名段为空串（三段保持）。
    public static function algNone(): string
    {
        $head = self::b64((string) json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $body = self::b64((string) json_encode(['sub' => 'USER-A', 'typ' => 'access'], JSON_UNESCAPED_SLASHES));

        return $head.'.'.$body.'.';
    }

    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
