<?php

namespace App\Services;

use App\Exceptions\AiUnavailable;

class AiQuestions
{
    public function __construct(private MockAiQuestions $mock, private OpenAiQuestions $openai) {}

    public static function provider(): string
    {
        $provider = config('ai.provider');

        return $provider === 'auto'
            ? (trim((string) config('ai.openai.key')) !== '' ? 'openai' : 'mock')
            : (string) $provider;
    }

    public function generate(string $description, array $fields = []): array
    {
        $baseline = $this->mock->generate($description, $fields);
        if (self::provider() === 'mock') {
            return $baseline;
        }
        if (self::provider() !== 'openai') {
            throw new AiUnavailable('Проверьте AI_PROVIDER в настройках backend.', 'ai_configuration', 503);
        }

        $result = $this->openai->generate($baseline['suggestedFields'], $baseline['missingFields']);

        // Card facts and missing fields are computed locally, never authored by the model.
        return array_replace($baseline, $result, [
            'mode' => 'openai',
            'model' => config('ai.openai.model'),
            'notice' => 'Вопросы подготовлены OpenAI. Проверьте их; сведения и публикацию подтверждает заказчик.',
        ]);
    }
}
