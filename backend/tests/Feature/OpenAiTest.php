<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenAiTest extends TestCase
{
    private const HEADERS = ['X-Demo-Role' => 'customer', 'X-Demo-Id' => '1'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true, 'ai.provider' => 'openai', 'ai.openai.key' => 'sk-test-not-a-real-key', 'ai.openai.model' => 'test-model']);
        Http::preventStrayRequests();
    }

    private function questions(): array
    {
        return ['topic' => 'schedule', 'questions' => [
            ['field' => 'users', 'question' => 'Кому нужен общий календарь консультаций?'],
            ['field' => 'materials', 'question' => 'Где сейчас хранится расписание консультаций?'],
            ['field' => 'successCriteria', 'question' => 'Как проверить актуальность расписания?'],
        ]];
    }

    private function envelope(?array $result = null): array
    {
        return ['status' => 'completed', 'output' => [
            ['type' => 'reasoning', 'summary' => []],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($result ?? $this->questions(), JSON_UNESCAPED_UNICODE)]]],
        ]];
    }

    private function ask(array $payload = [])
    {
        return $this->postJson('/api/ai/questions', $payload ?: ['description' => 'Нужно расписание консультаций'], self::HEADERS);
    }

    public function test_real_provider_uses_responses_and_never_generates_card_facts(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->envelope())]);
        $result = $this->ask(['description' => 'Нужно расписание', 'fields' => ['users' => 'Студенты', 'contact' => 'private@example.invalid']])
            ->assertOk()->assertJsonPath('data.mode', 'openai')->assertJsonPath('data.model', 'test-model')
            ->assertJsonPath('data.questions.0.id', 'clarify_users')
            ->assertJsonPath('data.questions.0.question', $this->questions()['questions'][0]['question'])
            ->assertJsonPath('data.suggestedFields', ['context' => 'Нужно расписание', 'users' => 'Студенты', 'contact' => 'private@example.invalid']);
        $this->assertNotContains('contact', $result->json('data.missingFields'));
        Http::assertSent(function (Request $request) {
            $input = json_decode($request['input'][0]['content'], true);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer sk-test-not-a-real-key')
                && $request['model'] === 'test-model' && $request['store'] === false
                && $request['max_output_tokens'] === 2500
                && $request['text']['format']['strict'] === true
                && $request['text']['format']['type'] === 'json_schema'
                && str_contains($request['instructions'], 'Do not invent facts')
                && $input['contactProvided'] === true
                && ! str_contains($request->body(), 'private@example.invalid')
                && $input['fields']['users'] === 'Студенты';
        });
        Http::assertSentCount(1);
    }

    public function test_auto_and_mock_modes_are_explicit_and_require_no_network(): void
    {
        config(['ai.provider' => 'auto', 'ai.openai.key' => '']);
        $this->ask()->assertOk()->assertJsonPath('data.mode', 'mock');
        config(['ai.provider' => 'mock', 'ai.openai.key' => 'sk-ignored']);
        $this->ask()->assertOk()->assertJsonPath('data.mode', 'mock');
        Http::assertNothingSent();
        config(['ai.provider' => 'auto']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->envelope())]);
        $this->ask()->assertOk()->assertJsonPath('data.mode', 'openai');
    }

    public function test_required_openai_mode_without_key_fails_honestly(): void
    {
        config(['ai.openai.key' => '']);
        $this->ask()->assertStatus(503)->assertJsonPath('code', 'ai_configuration')->assertJsonMissingPath('data');
        Http::assertNothingSent();
    }

    #[DataProvider('providerErrors')]
    public function test_provider_errors_are_sanitized_and_never_silently_replaced(int $providerStatus, int $status, string $code): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'SECRET sk-test-not-a-real-key']], $providerStatus)]);
        $this->ask()->assertStatus($status)->assertJsonPath('code', $code)->assertJsonMissingPath('data')->assertDontSee('SECRET')->assertDontSee('sk-test');
        Http::assertSentCount(1);
    }

    public static function providerErrors(): array
    {
        return [[401, 503, 'ai_configuration'], [403, 503, 'ai_configuration'], [404, 503, 'ai_configuration'], [400, 503, 'ai_configuration'], [429, 503, 'ai_quota'], [500, 502, 'ai_provider'], [302, 502, 'ai_provider']];
    }

    public function test_timeout_is_actionable_without_leaking_connection_details(): void
    {
        Http::fake(fn () => throw new ConnectionException('SECRET transport diagnostics'));
        $this->ask()->assertStatus(504)->assertJsonPath('code', 'ai_timeout')->assertDontSee('SECRET');
    }

    public function test_incomplete_and_refused_responses_are_not_questions(): void
    {
        Http::fakeSequence()->push(['status' => 'incomplete'])->push([
            'status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]],
        ]);
        $this->ask()->assertStatus(502)->assertJsonPath('code', 'ai_incomplete');
        $this->ask()->assertStatus(502)->assertJsonPath('code', 'ai_refusal');
    }

    #[DataProvider('malformedResults')]
    public function test_malformed_or_untrusted_output_is_rejected(string $json): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $json]]]],
        ])]);
        $this->ask()->assertStatus(502)->assertJsonPath('code', 'ai_invalid_response')->assertJsonMissingPath('data');
    }

    public static function malformedResults(): array
    {
        $question = ['field' => 'users', 'question' => 'Who will use the calendar?'];
        $valid = ['topic' => 'schedule', 'questions' => [$question, ['field' => 'materials', 'question' => 'What data is available?'], ['field' => 'contact', 'question' => 'Is a contact channel agreed?']]];

        return [
            ['not JSON'], ['null'], ['"string"'],
            [json_encode(['topic' => 'schedule', 'questions' => []])],
            [json_encode(['topic' => 'schedule', 'questions' => [$question, $question, $question]])],
            [json_encode(array_replace_recursive($valid, ['questions' => [0 => ['field' => 'status']]]))],
            [json_encode(array_replace_recursive($valid, ['questions' => [0 => ['question' => str_repeat('a', 601)]]]))],
            [json_encode($valid + ['suggestedFields' => ['users' => 'Invented users']])],
        ];
    }

    public function test_input_size_and_role_are_checked_before_calling_openai(): void
    {
        $this->ask(['description' => str_repeat('a', 10000), 'fields' => ['users' => str_repeat('b', 10000), 'materials' => 'x']])->assertUnprocessable();
        $this->postJson('/api/ai/questions', ['description' => 'Описание'])->assertUnauthorized();
        $this->postJson('/api/ai/questions', ['description' => 'Описание'], ['X-Demo-Role' => 'team', 'X-Demo-Id' => '1'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_ai_calls_are_rate_limited_before_spending_more_tokens(): void
    {
        config(['ai.requests_per_minute' => 1]);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->envelope())]);
        $this->ask()->assertOk();
        $this->ask()->assertStatus(429)->assertHeader('Retry-After');
        Http::assertSentCount(1);
    }

    public function test_check_command_cannot_mistake_mock_mode_for_live_openai(): void
    {
        config(['ai.provider' => 'mock']);
        $this->artisan('ai:check')->assertFailed();
        Http::assertNothingSent();
        config(['ai.provider' => 'openai']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->envelope())]);
        $this->artisan('ai:check')->expectsOutput('OpenAI OK: test-model')->assertSuccessful();
    }
}
