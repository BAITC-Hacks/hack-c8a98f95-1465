<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConfigureAiTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/alemedu-ai-config-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->directory);
        $this->app->useEnvironmentPath($this->directory);
        File::put($this->directory.'/.env', "APP_NAME=AlemEdu\n# Keep this comment\nAPP_KEY=existing-value\nAI_PROVIDER=auto\nOPENAI_API_KEY=\n");
    }

    protected function tearDown(): void
    {
        File::delete($this->directory.'/.env');
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_hidden_configuration_preserves_existing_settings_and_stores_key_locally(): void
    {
        $key = 'sk-test-not-a-real-key-for-unit-test';
        $this->artisan('ai:configure', ['--model' => 'gpt-5.4-mini'])
            ->expectsQuestion('OpenAI API key (hidden)', $key)->assertSuccessful();
        $contents = File::get($this->directory.'/.env');
        $env = Dotenv::parse($contents);
        $this->assertSame($key, $env['OPENAI_API_KEY']);
        $this->assertSame('openai', $env['AI_PROVIDER']);
        $this->assertSame('gpt-5.4-mini', $env['OPENAI_MODEL']);
        $this->assertSame('existing-value', $env['APP_KEY']);
        $this->assertStringContainsString('# Keep this comment', $contents);
        $this->assertSame(1, substr_count($contents, 'OPENAI_API_KEY='));
    }

    public function test_invalid_secret_cannot_inject_environment_variables(): void
    {
        $before = File::get($this->directory.'/.env');
        $this->artisan('ai:configure')->expectsQuestion('OpenAI API key (hidden)', "sk-bad\nAPP_DEBUG=true")->assertFailed();
        $this->assertSame($before, File::get($this->directory.'/.env'));
    }

    public function test_existing_key_requires_confirmation_to_replace(): void
    {
        File::put($this->directory.'/.env', "OPENAI_API_KEY=sk-existing-not-a-real-key\nAI_PROVIDER=openai\n");
        $before = File::get($this->directory.'/.env');
        $this->artisan('ai:configure')->expectsConfirmation('Replace the existing OpenAI key?', 'no')->assertSuccessful();
        $this->assertSame($before, File::get($this->directory.'/.env'));
    }
}
