<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\SnapshotCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// SnapshotCache 契约（rails app/services/auth/snapshot_cache.rb 逐语义移植）：
// 进程内 TTL 缓存（菜单/成员资格快照共用），TTL 1800s、clock 可注入、过期惰性删
// （miss/过期返 null；get 命中过期才删，无后台扫描）。
final class SnapshotCacheTest extends TestCase
{
    #[Test]
    public function ttl_constant_is_1800_seconds(): void
    {
        $this->assertSame(1800, SnapshotCache::TTL_SECONDS);
    }

    #[Test]
    public function put_then_get_round_trips(): void
    {
        $cache = new SnapshotCache;

        $cache->put('USER-A', ['menus' => ['m1']]);

        $this->assertSame(['menus' => ['m1']], $cache->get('USER-A'));
        $this->assertSame(1, $cache->size());
    }

    #[Test]
    public function miss_returns_null(): void
    {
        $this->assertNull((new SnapshotCache)->get('USER-Z'));
    }

    #[Test]
    public function put_replaces_value_and_refreshes_expiry(): void
    {
        $now = 1000;
        $cache = new SnapshotCache(clock: static function () use (&$now): int {
            return $now;
        });

        $cache->put('USER-A', 'v1');
        $now = 1000 + 1700; // 未过期（< 1800），第二次 put 刷新到期点
        $cache->put('USER-A', 'v2');
        $now = 1000 + 1799; // 仍在第二次 put 的窗口内

        $this->assertSame('v2', $cache->get('USER-A'));
    }

    #[Test]
    public function expired_entry_is_lazy_deleted_on_get(): void
    {
        $now = 1000;
        $cache = new SnapshotCache(clock: static function () use (&$now): int {
            return $now;
        });

        $cache->put('USER-A', 'snap');
        $now = 1000 + 1800; // 边界：now == expires_at 未过期（rails after? 语义：严格晚于才删）
        $this->assertSame('snap', $cache->get('USER-A'));

        $now = 1000 + 1801; // 严格晚于 → 过期：get 返 null 且条目即删
        $this->assertNull($cache->get('USER-A'));
        $this->assertSame(0, $cache->size());
    }

    #[Test]
    public function blank_user_and_null_value_are_ignored(): void
    {
        // rails return if user_id.nil? || value.nil? 镜像：空键/空值不写不读。
        $cache = new SnapshotCache;

        $cache->put('', 'x');
        $cache->put('USER-A', null);

        $this->assertSame(0, $cache->size());
        $this->assertNull($cache->get(''));
    }
}
