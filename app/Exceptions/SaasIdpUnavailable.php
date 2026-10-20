<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Auth::SaasIdpUnavailable 镜像：登录路径 saas IdP 不可达/5xx → 502 SAAS_UNAVAILABLE
// （REQ-2026-023：五仓统一码，不透传 saas 内部详情）。
final class SaasIdpUnavailable extends RuntimeException {}
