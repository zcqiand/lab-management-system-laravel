<?php

declare(strict_types=1);

namespace App\Support;

// 应用层时间戳（rails ApplicationRecord.now_iso 镜像，lab-springboot nowIso() 同源）。
// lab 库 created_at/updated_at 是 TEXT 列（V001 起历史设计），由应用层维护 ISO-8601
// UTC 串（恒 +00:00 偏移）——关框架自动 touch 防格式分叉。
final class Time
{
    // rails Time.now.utc.strftime('%Y-%m-%dT%H:%M:%S%:z') 镜像：%:z 在 UTC 恒为 +00:00。
    public static function nowIso(): string
    {
        return gmdate('Y-m-d\TH:i:s').'+00:00';
    }
}
