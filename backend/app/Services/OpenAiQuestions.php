<?php

namespace App\Services;

use App\Exceptions\AiUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use JsonException;

class OpenAiQuestions
{
    public function generate(array $fields, array $missingFields): array
    {
        $key = trim((string) config('ai.openai.key'));
        if ($key === '') {
            throw new AiUnavailable('OpenAI не настроен. Администратору нужно выполнить php artisan ai:configure в backend.', 'ai_configuration', 503);
        }

        $contactProvided = isset($fields['contact']);
        unset($fields['contact']);
        try {
            $response = Http::withToken($key)->acceptJson()
                ->connectTimeout(5)->timeout(config('ai.openai.timeout'))
                ->withOptions(['allow_redirects' => false])
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('ai.openai.model'),
                    'store' => false,
                    'max_output_tokens' => config('ai.openai.max_output_tokens'),
                    'instructions' => file_get_contents(resource_path('prompts/task-questions.txt')),
                    'input' => [[
                        'role' => 'user',
                        'content' => json_encode(compact('fields', 'missingFields', 'contactProvided'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ]],
                    'text' => ['format' => [
                        'type' => 'json_schema', 'name' => 'task_questions',
                        'strict' => true, 'schema' => $this->schema(),
                    ]],
                ]);
        } catch (ConnectionException) {
            throw new AiUnavailable('OpenAI не ответил вовремя. Попробуйте ещё раз; данные задачи не изменены.', 'ai_timeout', 504);
        }

        // Never expose provider bodies, request headers or credentials in API errors.
        if (in_array($response->status(), [400, 401, 403, 404], true)) {
            throw new AiUnavailable('Проверьте ключ OpenAI, доступ к модели и настройки backend.', 'ai_configuration', 503);
        }
        if ($response->status() === 429) {
            throw new AiUnavailable('Лимит OpenAI исчерпан. Проверьте баланс и лимиты API или повторите позже.', 'ai_quota', 503);
        }
        if (! $response->successful()) {
            throw new AiUnavailable('Сервис OpenAI временно недоступен. Попробуйте позже.', 'ai_provider');
        }
        if ($response->json('status') !== 'completed') {
            throw new AiUnavailable('OpenAI не завершил ответ. Попробуйте сократить описание и повторить запрос.', 'ai_incomplete');
        }

        $text = '';
        $output = $response->json('output');
        if (! is_array($output)) {
            throw $this->invalidResponse();
        }
        foreach ($output as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }
            foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $content) {
                if (! is_array($content)) {
                    continue;
                }
                if (($content['type'] ?? null) === 'refusal') {
                    throw new AiUnavailable('OpenAI не смог обработать это описание. Переформулируйте образовательную задачу.', 'ai_refusal');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $text .= $content['text'];
                }
            }
        }
        try {
            $result = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalidResponse();
        }
        $validator = Validator::make(['result' => $result], [
            'result' => ['required', 'array:topic,questions'],
            'result.topic' => ['required', Rule::in(['language', 'schedule', 'library', 'general'])],
            'result.questions' => ['required', 'array', 'min:3', 'max:7'],
            'result.questions.*' => ['required', 'array:field,question'],
            'result.questions.*.field' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(TaskScorer::CRITERIA))],
            'result.questions.*.question' => ['required', 'string', 'min:8', 'max:600'],
        ]);
        if ($validator->fails() || ! array_is_list($result['questions'])) {
            throw $this->invalidResponse();
        }

        return [
            'topic' => $result['topic'],
            'questions' => array_map(fn (array $question) => [
                'id' => 'clarify_'.$question['field'],
                'field' => $question['field'],
                'question' => trim($question['question']),
            ], $result['questions']),
        ];
    }

    private function invalidResponse(): AiUnavailable
    {
        return new AiUnavailable('OpenAI вернул ответ неподходящего формата. Попробуйте ещё раз.', 'ai_invalid_response');
    }

    private function schema(): array
    {
        return [
            'type' => 'object', 'additionalProperties' => false,
            'required' => ['topic', 'questions'],
            'properties' => [
                'topic' => ['type' => 'string', 'enum' => ['language', 'schedule', 'library', 'general']],
                'questions' => ['type' => 'array', 'minItems' => 3, 'maxItems' => 7, 'items' => [
                    'type' => 'object', 'additionalProperties' => false,
                    'required' => ['field', 'question'],
                    'properties' => [
                        'field' => ['type' => 'string', 'enum' => array_keys(TaskScorer::CRITERIA)],
                        'question' => ['type' => 'string'],
                    ],
                ]],
            ],
        ];
    }
}
