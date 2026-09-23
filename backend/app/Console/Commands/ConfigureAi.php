<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ConfigureAi extends Command
{
    protected $signature = 'ai:configure {--model=gpt-5.4-mini : Responses model with Structured Outputs}';

    protected $description = 'Store an OpenAI key in backend .env using hidden input';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run interactively. Never pass an API key as a command argument.');

            return self::FAILURE;
        }
        $model = (string) $this->option('model');
        if (! preg_match('/\A[A-Za-z0-9._:-]{1,120}\z/', $model)) {
            $this->error('Invalid model name.');

            return self::FAILURE;
        }
        $path = $this->laravel->environmentFilePath();
        if (! File::exists($path)) {
            $this->error('Prepare backend .env first using composer setup or start.ps1.');

            return self::FAILURE;
        }
        $contents = File::get($path);
        $existing = Dotenv::parse($contents);
        if (! empty($existing['OPENAI_API_KEY']) && ! $this->confirm('Replace the existing OpenAI key?', false)) {
            return self::SUCCESS;
        }
        $key = trim((string) $this->secret('OpenAI API key (hidden)', false));
        if (! preg_match('/\Ask-[A-Za-z0-9_-]{16,}\z/', $key)) {
            $this->error('Invalid API key format. No changes were made.');

            return self::FAILURE;
        }
        foreach (['AI_PROVIDER' => 'openai', 'OPENAI_API_KEY' => $key, 'OPENAI_MODEL' => $model] as $name => $value) {
            $pattern = '/^[\t ]*(?:export[\t ]+)?'.preg_quote($name, '/').'[\t ]*=.*$/m';
            $line = $name.'='.$value;
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents)
                : rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }
        File::replace($path, $contents, 0600);
        unset($key, $contents, $existing);
        $this->callSilent('config:clear');
        $this->info('OpenAI configured. Key saved only in backend .env.');
        $this->line('Restart backend, then run: php artisan ai:check (one billed API request).');

        return self::SUCCESS;
    }
}
