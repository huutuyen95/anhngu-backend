<?php

namespace App\Services\Ai;

/**
 * Chọn driver theo cấu hình của cô.
 *
 * Thêm nhà cung cấp mới = thêm một dòng vào `match` + một lựa chọn ở `ai.provider`
 * (config/appsettings.php). Không khớp cái nào → NullDriver, hệ thống chạy như chưa có AI.
 */
final class AiDriverManager
{
    public function __construct(private readonly AiConfig $config) {}

    public function driver(): GradingDriver
    {
        return match ($this->config->provider()) {
            'openai' => new OpenAiDriver($this->config),
            // 'claude' => new ClaudeDriver($this->config),
            // 'gemini' => new GeminiDriver($this->config),
            default => new NullDriver,
        };
    }
}
