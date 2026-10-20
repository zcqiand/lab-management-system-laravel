<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

// GET /api/_frontend-bind/snapshot —— 生成器 DTO 契约面（lab-springboot 无
// @RestController 实现；Security 过滤链先于 404 handler，匿名请求 401 而非 404）。
// 2026-09-29 review Important：类注释声称挂 JwtGuard 但未 include，匿名曾 404。
final class FrontendBindTest extends TestCase
{
    #[Test]
    public function snapshot_anonymous_request_is_401_security_chain_before_404_handler(): void
    {
        $response = $this->getJson('/api/_frontend-bind/snapshot');

        $response->assertUnauthorized();
        $this->assertSame('INVALID_CREDENTIALS', $response->json('code'));
    }
}
