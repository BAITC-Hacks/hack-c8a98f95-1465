<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SingleServerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/alemedu-spa-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->directory.'/build', 0755, true);
        $this->app->usePublicPath($this->directory);
        File::put($this->directory.'/build/index.html', '<!doctype html><html><body><div id="app">AlemEdu</div></body></html>');
    }

    protected function tearDown(): void
    {
        File::delete($this->directory.'/build/index.html');
        rmdir($this->directory.'/build');
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_frontend_history_routes_serve_the_same_built_app(): void
    {
        foreach (['/', '/catalog', '/workspace', '/guide', '/tasks/new', '/tasks/2', '/tasks/2/edit', '/tasks/2/offers'] as $path) {
            $this->get($path)->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        }
    }

    public function test_spa_fallback_never_masks_api_errors_or_sensitive_paths(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJsonPath('data.status', 'ok');
        $this->getJson('/api/not-found')->assertNotFound()->assertJsonStructure(['message']);
        foreach (['/.env', '/.git/config', '/composer.json', '/build/missing.js'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_unbuilt_frontend_returns_an_actionable_error(): void
    {
        File::delete($this->directory.'/build/index.html');
        $this->get('/')->assertStatus(503);
    }
}
