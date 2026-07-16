<?php

namespace Tests\Feature\Cache;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisCacheTest extends TestCase
{
    public function test_redis_is_available(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('PHP redis extension not installed');
        }

        try {
            Redis::ping();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }

        $this->assertTrue(true, 'Redis is reachable');
    }

    public function test_cache_set_and_get(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('PHP redis extension not installed');
        }

        try {
            Redis::ping();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis server not available');
        }

        config(['cache.default' => 'redis']);
        Cache::flush();

        Cache::put('test_key', 'test_value', 60);
        $this->assertEquals('test_value', Cache::get('test_key'));

        Cache::flush();
    }

    public function test_cache_tags_work(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('PHP redis extension not installed');
        }

        try {
            Redis::ping();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis server not available');
        }

        config(['cache.default' => 'redis']);
        Cache::flush();

        Cache::tags(['users', 'admin'])->put('user_1', 'Alice', 60);
        $this->assertEquals('Alice', Cache::tags(['users'])->get('user_1'));

        Cache::flush();
    }
}
