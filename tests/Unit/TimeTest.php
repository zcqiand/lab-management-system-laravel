<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Time;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Time::nowIso 契约：ISO-8601 UTC 串（rails ApplicationRecord.now_iso 镜像：
// Time.now.utc.strftime('%Y-%m-%dT%H:%M:%S%:z')，UTC 恒 +00:00）。lab 库
// created_at/updated_at 是 TEXT 列，由应用层维护本格式（关框架自动 touch 防格式分叉）。
final class TimeTest extends TestCase
{
    #[Test]
    public function now_iso_is_iso8601_utc_with_explicit_offset(): void
    {
        $before = gmdate('Y-m-d\TH:i:s');
        $iso = Time::nowIso();
        $after = gmdate('Y-m-d\TH:i:s');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $iso
        );
        // 落在采样窗口内（同秒或跨秒边界），证明是当下时钟而非写死。
        $this->assertGreaterThanOrEqual($before, substr($iso, 0, 19));
        $this->assertLessThanOrEqual($after, substr($iso, 0, 19));
    }
}
