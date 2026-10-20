<?php

declare(strict_types=1);

namespace App\Exceptions\Jwt;

use RuntimeException;

// JWT 验签/形状无效（rails JWT::DecodeError / "unexpected typ" 同语义：坏段数、
// alg 混淆、坏签名、坏 payload、iss 不符、typ 不符）→ 调用方统一 401
// "invalid token: {message}"。
final class TokenInvalid extends RuntimeException {}
