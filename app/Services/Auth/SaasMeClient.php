<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\InvalidGrant;
use App\Exceptions\UpstreamUnavailable;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// saas /me 系真对接（lab-springboot SaasMeClient 镜像，rails app/services/auth/
// saas_me_client.rb 逐语义移植）：
// /api/v1/me、/me/tenants、/me/menus（Map<appCode, tree>）、/admin/tenants（显示名富化）。
// 401/4xx→InvalidGrant；5xx+网络→UpstreamUnavailable（同 SaasAuthClient 分流）。
//
// 非 final（测试桩匿名类 extends 本类整件替换——真实构造不跑，永不真打 HTTP）。
class SaasMeClient
{
    private string $saasBase;

    public function __construct()
    {
        $base = Env::get('LAB_SAAS_BASE_URL');
        if (! is_string($base) || $base === '') {
            throw new RuntimeException('LAB_SAAS_BASE_URL 缺失（fail-fast——suite-hard-rules §1，禁 env 默认值兜底）');
        }
        $this->saasBase = rtrim($base, '/');
    }

    // @return array<string, mixed> {id, email, displayName, memberships, currentTenantId}
    public function whoami(string $saasAccessToken): array
    {
        /** @var array<string, mixed> */
        return (array) $this->getJson('/api/v1/me', $saasAccessToken);
    }

    // @return list<array<string, mixed>>
    public function listMyTenants(string $saasAccessToken): array
    {
        $json = $this->getJson('/api/v1/me/tenants', $saasAccessToken);

        return is_array($json) ? $json : [];
    }

    // saas /me/menus 返 Map<appCode, List<EffectiveMenuNode>>，按 appCode 取子树。
    //
    // @return list<array<string, mixed>>
    public function listMyMenus(string $saasAccessToken, string $appCode): array
    {
        $map = $this->getJson('/api/v1/me/menus', $saasAccessToken);
        if (! is_array($map) || $map === []) {
            return [];
        }

        $subtree = $map[$appCode] ?? [];

        return is_array($subtree) ? $subtree : [];
    }

    // 平台租户列表（display-name 富化；分页壳 {items,total}）。
    //
    // @return list<array<string, mixed>>
    public function listPlatformTenants(string $saasAccessToken): array
    {
        $page = $this->getJson('/api/v1/admin/tenants?page=0&pageSize=100', $saasAccessToken);
        if (! is_array($page) || ! isset($page['items']) || ! is_array($page['items'])) {
            return [];
        }

        return $page['items'];
    }

    // @return array<string, mixed>|list<array<string, mixed>>|null
    private function getJson(string $pathWithQuery, string $token): ?array
    {
        try {
            $res = Http::timeout(10)->withToken($token)->get($this->saasBase.$pathWithQuery);
        } catch (ConnectionException|TransferException $e) {
            throw new UpstreamUnavailable('saas connect failed: '.$e->getMessage());
        }

        if ($res->successful()) {
            $json = $res->json();

            return is_array($json) ? $json : null;
        }

        $status = $res->status();
        if ($status < 500) {
            throw new InvalidGrant('saas '.$status.' '.$this->truncate((string) $res->body()));
        }

        throw new UpstreamUnavailable('saas '.$status);
    }

    private function truncate(string $s, int $max = 200): string
    {
        return mb_strlen($s) <= $max ? $s : mb_substr($s, 0, $max).'...';
    }
}
