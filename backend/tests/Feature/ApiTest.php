<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Task;
use App\Models\Team;
use App\Services\TaskScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed();
    }

    private function customer(int $id = 1): array
    {
        return ['X-Demo-Role' => 'customer', 'X-Demo-Id' => (string) $id];
    }

    private function team(int $id = 1): array
    {
        return ['X-Demo-Role' => 'team', 'X-Demo-Id' => (string) $id];
    }

    private function draft(array $extra = []): int
    {
        return $this->postJson('/api/tasks', array_merge([
            'title' => 'Практика английского', 'organization' => 'Демо университет «Алем»',
            'region' => 'Алматы', 'category' => 'Языки', 'scope' => 'institution',
        ], $extra), $this->customer())->assertCreated()->json('data.id');
    }

    public function test_full_workflow_from_ai_to_multiple_selected_teams(): void
    {
        $description = 'Первокурсникам трудно практиковать разговорный английский.';
        $ai = $this->postJson('/api/ai/questions', ['description' => $description], $this->customer())
            ->assertOk()->assertJsonPath('data.mode', 'mock');
        $this->assertGreaterThanOrEqual(3, count($ai->json('data.questions')));
        $id = $this->draft(['context' => $description]);
        $this->getJson("/api/tasks/{$id}", $this->customer())->assertOk()->assertJsonPath('data.score', 0);
        $this->getJson("/api/tasks/{$id}")->assertNotFound();
        $fields = [
            'context' => $description, 'users' => 'Первокурсники.',
            'materials' => 'Синтетические учебные диалоги.', 'constraints' => 'Пилот за месяц.',
            'expectedOutcome' => 'Веб-прототип разговорной практики.',
            'successCriteria' => 'Пять тестовых диалогов проходят без ошибок.',
            'contact' => 'demo@example.invalid, еженедельный созвон.',
            'confirmedFields' => array_keys(TaskScorer::CRITERIA),
        ];
        $this->putJson("/api/tasks/{$id}", $fields, $this->customer())
            ->assertOk()->assertJsonPath('data.score', 100)->assertJsonPath('data.status', 'draft');
        $this->postJson("/api/tasks/{$id}/publish", ['confirmed' => true], $this->customer())
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson('/api/tasks')->assertJsonFragment(['id' => $id, 'title' => 'Практика английского']);
        $offerIds = [];
        foreach ([1, 2] as $teamId) {
            $offerIds[] = $this->postJson("/api/tasks/{$id}/offers", [
                'idea' => 'Создадим тренажёр.', 'plan' => 'Исследование, прототип, проверка.',
                'timeline' => 'Три недели.',
            ], $this->team($teamId))->assertCreated()->assertJsonPath('data.decision', 'pending')->json('data.id');
        }
        $this->getJson("/api/tasks/{$id}/offers", $this->customer())->assertOk()->assertJsonCount(2, 'data');
        foreach ($offerIds as $offerId) {
            $this->patchJson("/api/offers/{$offerId}/decision", ['decision' => 'selected'], $this->customer())
                ->assertOk()->assertJsonPath('data.decision', 'selected');
        }
        $this->assertSame(2, Offer::where('task_id', $id)->where('decision', 'selected')->count());
        $this->patchJson("/api/offers/{$offerIds[0]}/decision", ['decision' => 'rejected'], $this->customer())
            ->assertOk()->assertJsonPath('data.decision', 'rejected');
    }

    public function test_zero_score_task_can_be_published_and_any_team_can_respond_repeatedly(): void
    {
        $id = $this->draft();
        $this->postJson("/api/tasks/{$id}/publish", ['confirmed' => true], $this->customer())
            ->assertOk()->assertJsonPath('data.score', 0)->assertJsonPath('data.readinessLevel', 'draft');
        for ($i = 0; $i < 2; $i++) {
            $this->postJson("/api/tasks/{$id}/offers", [
                'idea' => 'Идея', 'plan' => 'План', 'timeline' => 'Две недели',
            ], $this->team(2))->assertCreated()->assertJsonPath('data.decision', 'pending');
        }
        $this->getJson("/api/tasks/{$id}")->assertOk()->assertJsonPath('data.offersCount', 2);
    }

    public function test_publishing_requires_explicit_confirmation_and_identity_fields(): void
    {
        $id = $this->draft();
        $this->postJson("/api/tasks/{$id}/publish", [], $this->customer(), JSON_FORCE_OBJECT)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->postJson("/api/tasks/{$id}/publish", ['confirmed' => false], $this->customer())->assertUnprocessable();
        $this->assertDatabaseHas('tasks', ['id' => $id, 'status' => 'draft']);
        $this->putJson("/api/tasks/{$id}", ['organization' => ''], $this->customer())->assertOk();
        $this->postJson("/api/tasks/{$id}/publish", ['confirmed' => true], $this->customer())
            ->assertUnprocessable()->assertJsonValidationErrors('organization');
    }

    public function test_edit_invalidates_changed_fields_and_preserves_other_confirmations(): void
    {
        $id = $this->draft(['context' => 'Проблема', 'materials' => 'Данные', 'confirmedFields' => ['context', 'materials']]);
        $this->postJson("/api/tasks/{$id}/publish", ['confirmed' => true], $this->customer())->assertJsonPath('data.score', 40);
        $this->putJson("/api/tasks/{$id}", ['context' => 'Другая проблема'], $this->customer())
            ->assertOk()->assertJsonPath('data.score', 20)->assertJsonPath('data.confirmedFields', ['materials'])
            ->assertJsonPath('data.confirmedAt', null)->assertJsonPath('data.status', 'published');
        $this->getJson("/api/tasks/{$id}")->assertOk()->assertJsonPath('data.score', 20);
        $this->putJson("/api/tasks/{$id}", ['materials' => null, 'confirmedFields' => ['context']], $this->customer())
            ->assertOk()->assertJsonPath('data.materials', '')->assertJsonPath('data.score', 20);
        $this->assertDatabaseHas('tasks', ['id' => $id, 'score' => 20]);
    }

    public function test_invalid_updates_are_atomic_and_server_fields_cannot_be_overridden(): void
    {
        $id = $this->draft(['context' => 'Проблема', 'confirmedFields' => ['context']]);
        $this->putJson("/api/tasks/{$id}", ['title' => 'Новое имя', 'confirmedFields' => ['materials']], $this->customer())
            ->assertUnprocessable()->assertJsonValidationErrors('confirmedFields');
        $this->getJson("/api/tasks/{$id}", $this->customer())->assertJsonPath('data.title', 'Практика английского')->assertJsonPath('data.score', 20);
        foreach ([['score' => 100], ['status' => 'published'], ['ownerId' => 2], ['scope' => 'private'], ['title' => '   '], ['confirmedFields' => ['unknown']], ['confirmedFields' => ['context', 'context']]] as $invalid) {
            $this->putJson("/api/tasks/{$id}", $invalid, $this->customer())->assertUnprocessable();
        }
    }

    public function test_catalog_hides_drafts_supports_filters_search_and_sort(): void
    {
        $data = $this->getJson('/api/tasks')->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([100, 90, 75, 60], array_column($data, 'score'));
        $this->getJson('/api/tasks?scope=institution')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/tasks?'.http_build_query(['region' => 'Шымкент', 'category' => 'Библиотека']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 3);
        $this->getJson('/api/tasks?'.http_build_query(['q' => 'УЧЕБНЫХ КНИГ']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 3);
        $this->getJson('/api/tasks?scope=private')->assertUnprocessable();
        $this->getJson('/api/tasks?sort=bad')->assertUnprocessable();
        $this->getJson('/api/tasks?'.http_build_query(['q' => 'Несуществующая задача']))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/tasks?sort=newest')->assertOk()->assertJsonPath('data.0.id', 5);
        $preferred = $this->getJson('/api/tasks?'.http_build_query(['preferredOrganization' => 'Демо университет «Алем»']))
            ->assertOk()->assertJsonCount(4, 'data')->json('data');
        $this->assertSame([4, 2, 5, 3], array_column($preferred, 'id'));
    }

    public function test_permissions_protect_customer_actions_and_drafts(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Задача'])->assertUnauthorized();
        $this->postJson('/api/tasks', ['title' => 'Задача'], $this->team())->assertForbidden();
        $this->postJson('/api/tasks', ['title' => 'Задача'], $this->customer(999))->assertUnauthorized();
        $this->putJson('/api/tasks/1', ['title' => 'Чужая правка'], $this->customer(2))->assertForbidden();
        $this->postJson('/api/tasks/1/publish', ['confirmed' => true], $this->customer(2))->assertForbidden();
        $this->getJson('/api/tasks/1', $this->customer(2))->assertNotFound();
        $this->getJson('/api/tasks/1', $this->team())->assertNotFound();
        $this->getJson('/api/tasks/1', $this->customer())->assertOk();
        $this->getJson('/api/tasks/5')->assertOk();
        $this->getJson('/api/tasks/2/offers', $this->customer(2))->assertForbidden();
        $this->getJson('/api/tasks/2/offers', $this->team())->assertForbidden();
        $this->patchJson('/api/offers/1/decision', ['decision' => 'selected'], $this->customer(2))->assertForbidden();
        $this->patchJson('/api/offers/1/decision', ['decision' => 'selected'], $this->team())->assertForbidden();
        $this->assertDatabaseHas('offers', ['id' => 1, 'decision' => 'pending']);
    }

    public function test_offers_validate_fields_links_and_never_accept_client_decisions(): void
    {
        $valid = ['idea' => 'Идея', 'plan' => 'План', 'timeline' => 'Месяц'];
        $this->postJson('/api/tasks/1/offers', $valid, $this->team())->assertNotFound();
        $this->postJson('/api/tasks/2/offers', [], $this->team(), JSON_FORCE_OBJECT)->assertUnprocessable()->assertJsonValidationErrors(['idea', 'plan', 'timeline']);
        foreach ([['prototypeLink' => 'javascript:alert(1)'], ['teamId' => 2], ['decision' => 'selected'], ['idea' => '   ']] as $invalid) {
            $this->postJson('/api/tasks/2/offers', array_merge($valid, $invalid), $this->team())->assertUnprocessable();
        }
        $this->postJson('/api/tasks/2/offers', array_merge($valid, ['prototypeLink' => 'https://example.invalid/prototype']), $this->team())
            ->assertCreated()->assertJsonPath('data.teamId', 1)->assertJsonPath('data.decision', 'pending');
        $this->patchJson('/api/offers/1/decision', ['decision' => 'pending'], $this->customer())->assertUnprocessable();
    }

    public function test_demo_profiles_dashboard_and_seed_are_consistent_and_non_destructive(): void
    {
        $this->getJson('/api/demo/profiles')->assertOk()->assertJsonCount(2, 'data.customers')->assertJsonCount(5, 'data.teams');
        $this->getJson('/api/demo/briefs')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/teams')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/my/tasks', $this->customer())->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/my/tasks', $this->customer(2))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/my/offers', $this->team(2))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.teamId', 2);
        $this->putJson('/api/tasks/1', ['title' => 'Сохранённая правка'], $this->customer())->assertOk();
        $this->seed();
        $this->assertSame(5, Task::count());
        $this->assertSame(5, Team::count());
        $this->assertSame(5, Offer::count());
        $this->assertDatabaseHas('tasks', ['id' => 1, 'title' => 'Сохранённая правка']);
    }

    public function test_bad_json_and_missing_resources_return_json_errors(): void
    {
        $response = $this->call('POST', '/api/tasks', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_DEMO_ROLE' => 'customer', 'HTTP_X_DEMO_ID' => '1',
        ], '{broken');
        $response->assertStatus(400)->assertJsonStructure(['message']);
        $this->call('POST', '/api/tasks', [], [], [], ['CONTENT_TYPE' => 'application/json'], '[]')->assertStatus(400);
        $this->get('/api/tasks/999')->assertNotFound()->assertJsonStructure(['message']);
        $this->get('/api/does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
        $this->deleteJson('/api/tasks/1')->assertStatus(405)->assertJsonStructure(['message']);
    }

    public function test_ai_rejects_invalid_inputs_and_preserves_only_supplied_facts(): void
    {
        $this->postJson('/api/ai/questions', ['description' => ' '], $this->customer())->assertUnprocessable();
        $this->postJson('/api/ai/questions', ['description' => ['bad']], $this->customer())->assertUnprocessable();
        $this->postJson('/api/ai/questions', ['description' => 'Описание', 'fields' => ['invented' => 'Нет']], $this->customer())->assertUnprocessable();
        $response = $this->postJson('/api/ai/questions', ['description' => 'Нужно расписание', 'fields' => ['materials' => 'Таблица']], $this->customer())->assertOk();
        $response->assertJsonPath('data.topic', 'schedule')->assertJsonPath('data.suggestedFields', ['context' => 'Нужно расписание', 'materials' => 'Таблица']);
    }

    public function test_cors_accepts_the_vue_dev_origin(): void
    {
        $this->options('/api/tasks', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,x-demo-role,x-demo-id',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_demo_mode_can_be_disabled(): void
    {
        config(['demo.enabled' => false]);
        $this->postJson('/api/tasks', ['title' => 'Задача'], $this->customer())->assertStatus(503);
        $this->getJson('/api/demo/profiles')->assertStatus(503);
        $this->getJson('/api/tasks/1', $this->customer())->assertNotFound();
        $this->getJson('/api/tasks')->assertOk();
    }

    public function test_published_identity_fields_cannot_be_cleared_and_republish_is_idempotent(): void
    {
        $publishedAt = $this->getJson('/api/tasks/2')->json('data.publishedAt');
        $this->putJson('/api/tasks/2', ['organization' => null], $this->customer())
            ->assertUnprocessable()->assertJsonValidationErrors('organization');
        $this->postJson('/api/tasks/2/publish', ['confirmed' => true], $this->customer())
            ->assertOk()->assertJsonPath('data.publishedAt', $publishedAt)->assertJsonPath('data.score', 60);
        $this->putJson('/api/tasks/2', ['confirmedFields' => []], $this->customer())
            ->assertOk()->assertJsonPath('data.score', 0)->assertJsonPath('data.confirmedAt', null);
        $this->getJson('/api/tasks/2')->assertOk()->assertJsonPath('data.status', 'published');
    }

    public function test_api_rejects_non_json_payloads(): void
    {
        $this->call('POST', '/api/tasks', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'title=Task')->assertStatus(415)->assertJsonStructure(['message']);
    }
}
