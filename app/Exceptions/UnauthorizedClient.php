<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Auth::SaasAuthClient::UnauthorizedClient 镜像：saas oauth 客户端身份无效（401）
// → 认证域服务层归并 InvalidCredentials（401，不透传 saas 内部详情）。
// 与 InvalidGrant（4xx→400 INVALID_GRANT，code 重放/过期）分流。
final class UnauthorizedClient extends RuntimeException {}
