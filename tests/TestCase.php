<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ép mọi test dùng sqlite in-memory.
     *
     * Docker (env_file) đặt DB_CONNECTION=mysql thành biến môi trường OS; ở PHPUnit 11 các
     * <env> trong phpunit.xml KHÔNG override được biến OS đó, nên nếu không ép ở đây thì
     * RefreshDatabase sẽ chạy trên MySQL DEV thật và xoá sạch dữ liệu. Ép ngay sau khi app
     * khởi tạo, trước khi RefreshDatabase migrate.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);
    }
}
