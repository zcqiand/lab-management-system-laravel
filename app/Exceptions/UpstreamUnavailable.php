<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

// Auth::SaasAuthClient::UpstreamUnavailable 镜像：sso 链路上游不可达 → 502
// SAAS_UPSTREAM_ERROR（与登录路径 SaasIdpUnavailable 的 SAAS_UNAVAILABLE 分流）。
final class UpstreamUnavailable extends RuntimeException {}
