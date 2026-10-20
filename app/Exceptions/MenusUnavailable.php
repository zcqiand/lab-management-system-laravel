<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Auth::MenusUnavailable 镜像：菜单快照不可用 → 503 MENUS_UNAVAILABLE
// （lab-springboot GlobalExceptionHandler 镜像）。
final class MenusUnavailable extends RuntimeException {}
