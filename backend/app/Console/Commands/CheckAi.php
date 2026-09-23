<?php

namespace App\Console\Commands;

use App\Exceptions\AiUnavailable;
use App\Services\AiQuestions;
use Illuminate\Console\Command;

class CheckAi extends Command
{
    protected $signature = 'ai:check';

    protected $description = 'Verify OpenAI with one small billed request containing synthetic task data';

    public function handle(AiQuestions $ai): int
    {
        if (AiQuestions::provider() !== 'openai') {
            $this->error('OpenAI is not enabled. Run: php artisan ai:configure');

            return self::FAILURE;
        }
        try {
            $result = $ai->generate('Студентам нужен общий календарь консультаций преподавателей.');
        } catch (AiUnavailable $exception) {
            $this->error($exception->errorCode.': '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info('OpenAI OK: '.$result['model']);
        foreach ($result['questions'] as $question) {
            $this->line($question['field'].': '.$question['question']);
        }

        return self::SUCCESS;
    }
}
