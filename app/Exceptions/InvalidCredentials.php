<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// ApplicationController::InvalidCredentials 的 Laravel 镜像：→ 401 INVALID_CREDENTIALS。
// 未知用户与错密码同形（不泄露存在性）。
final class InvalidCredentials extends RuntimeException {}
