<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Closure;

// 进程内 TTL 快照缓存（rails app/services/auth/snapshot_cache.rb 逐语义移植：
// MenuSnapshotCache/MembershipSnapshotCache 合并镜像——同款 30min TTL + 按 userId
// 存取，miss/过期返 null）。菜单/成员资格快照共用，Task 5（auth）消费。
final class SnapshotCache
{
    public const TTL_SECONDS = 30 * 60;

    private int $ttlSeconds;

    /** @var Closure(): int */
    private Closure $clock;

    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $store = [];

    public function __construct(int $ttlSeconds = self::TTL_SECONDS, ?Closure $clock = null)
    {
        $this->ttlSeconds = $ttlSeconds;
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function put(string $userId, mixed $value): void
    {
        // rails return if user_id.nil? || value.nil? 镜像：空键/空值不写。
        if ($userId === '' || $value === null) {
            return;
        }

        $this->store[$userId] = ['value' => $value, 'expires_at' => ($this->clock)() + $this->ttlSeconds];
    }

    public function get(string $userId): mixed
    {
        if ($userId === '') {
            return null;
        }

        $entry = $this->store[$userId] ?? null;
        if ($entry === null) {
            return null;
        }

        // 过期惰性删（rails @clock.now.after?(expires_at) 镜像：严格晚于才删）——
        // 无后台扫描，get 命中过期条目时才清。
        if (($this->clock)() > $entry['expires_at']) {
            unset($this->store[$userId]);

            return null;
        }

        return $entry['value'];
    }

    public function size(): int
    {
        return count($this->store);
    }
}
