<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// ApplicationController::AccessDenied 的 Laravel 镜像：→ 403 FORBIDDEN。
// 典型来源：角色/权限不满足、租户缝越权（lab 全路由无 tenant 路径段，JwtGuard
// 不做 verifyPathTenant——与 saas TenantGuard 的有意差异）。
final class AccessDenied extends RuntimeException {}
