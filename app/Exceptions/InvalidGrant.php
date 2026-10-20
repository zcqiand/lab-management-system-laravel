<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Auth::SaasAuthClient::InvalidGrant 镜像：saas oauth grant 无效（code 重放/过期）
// → 400 INVALID_GRANT。
final class InvalidGrant extends RuntimeException {}
