<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiLayeringTest extends TestCase
{
    #[DataProvider('controllerFiles')]
    public function test_api_controllers_do_not_validate_or_query_database_directly(string $file): void
    {
        $source = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression('/->validate\s*\(/', $source, "Inline validation found in {$file}");
        $this->assertStringNotContainsString('Facades\\DB', $source, "DB facade found in {$file}");
        $this->assertDoesNotMatchRegularExpression(
            '/\$[A-Za-z_][A-Za-z0-9_]*->(?:load|fresh|refresh|save|delete|update|create|sync|attach|detach|forceDelete|restore|increment)\s*\(/',
            $source,
            "Direct model persistence found in {$file}",
        );

        foreach ($this->modelImports($source) as $model) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b'.preg_quote($model, '/').'::(?:query|where|whereIn|with|find|findOrFail|create|updateOrCreate|first|firstOrFail|count|pluck|select|destroy)\s*\(/',
                $source,
                "Direct {$model} query found in {$file}",
            );
        }

        if (str_contains($source, 'use Illuminate\\Http\\Request;')) {
            $this->assertDoesNotMatchRegularExpression(
                '/\$request->(?:input|only|query)\s*\(/',
                $source,
                "Unvalidated input is read from a base Request in {$file}",
            );
        }
    }

    #[DataProvider('serviceFiles')]
    public function test_services_delegate_database_access_to_repositories(string $file): void
    {
        $source = file_get_contents($file);

        $this->assertStringNotContainsString('Facades\\DB', $source, "DB facade found in {$file}");
        foreach ($this->modelImports($source) as $model) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b'.preg_quote($model, '/').'::(?:query|where|whereIn|whereHas|with|find|findOrFail|create|updateOrCreate|first|firstOrFail|count|pluck|select|destroy)\s*\(/',
                $source,
                "Direct {$model} query found in {$file}",
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\$[A-Za-z_][A-Za-z0-9_]*->(?:load|fresh|refresh|save|delete|update|sync|attach|detach|forceDelete|restore|increment)\s*\(/',
            $source,
            "Direct model persistence found in {$file}",
        );
    }

    public static function controllerFiles(): array
    {
        return self::files(dirname(__DIR__, 2).'/app/Http/Controllers/Api');
    }

    public static function serviceFiles(): array
    {
        return self::files(dirname(__DIR__, 2).'/app/Services');
    }

    private static function files(string $directory): array
    {
        $files = glob($directory.'/*.php') ?: [];

        return array_combine($files, array_map(fn (string $file) => [$file], $files));
    }

    private function modelImports(string $source): array
    {
        preg_match_all('/^use App\\\\Models\\\\([^;]+);$/m', $source, $matches);

        return $matches[1] ?? [];
    }
}
