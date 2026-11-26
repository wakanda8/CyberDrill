<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_old_questions_urls_return_404_for_admins(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/questions')->assertNotFound();
        $this->get('/questions/create')->assertNotFound();
        $this->get('/questions/1')->assertNotFound();
        $this->post('/questions', $this->spamPayload())->assertNotFound();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_scenario_forms_post_to_the_admin_urls(): void
    {
        $question = $this->spamQuestion();
        $this->actingAs(User::factory()->admin()->create());

        $create = $this->get('/admin/questions/create');
        $edit = $this->get("/admin/questions/{$question->id}/edit?module=spam");

        $create->assertOk()->assertSee('action="'.url('/admin/questions').'"', false);
        $edit->assertOk()
            ->assertSee('action="'.url("/admin/questions/{$question->id}").'"', false)
            ->assertSee('href="'.url('/admin/questions').'"', false);
    }

    public function test_creating_a_scenario_returns_to_the_admin_scenario_list(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post('/admin/questions', $this->spamPayload());

        $response->assertRedirect('/admin/questions')->assertSessionHas('success', 'Spam Scenario Created!');
        $this->assertDatabaseHas('questions', ['module' => 'spam', 'key' => 'admin_spam', 'title' => 'Prize email']);
    }

    public function test_updating_a_scenario_returns_to_the_admin_scenario_list(): void
    {
        $question = $this->spamQuestion();
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->put("/admin/questions/{$question->id}", $this->spamPayload(['title' => 'Edited title']));

        $response->assertRedirect('/admin/questions')->assertSessionHas('success', 'Spam Scenario Updated Successfully!');
        $this->assertSame('Edited title', $question->fresh()->title);
    }

    public function test_deleting_a_scenario_returns_to_the_admin_scenario_list(): void
    {
        $question = $this->spamQuestion();
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->delete("/admin/questions/{$question->id}", ['module' => 'spam']);

        $response->assertRedirect('/admin/questions')->assertSessionHas('success', 'Scenario Deleted Successfully!');
        $this->assertModelMissing($question);
    }

    public function test_creating_with_an_unknown_module_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post('/admin/questions', $this->spamPayload(['module_type' => 'quiz']));

        $response->assertSessionHasErrors(['module_type' => 'The selected module type is invalid.'])
            ->assertSessionMissing('success');
        $this->assertDatabaseCount('questions', 0);
    }

    public function test_updating_without_a_module_changes_nothing(): void
    {
        $question = $this->spamQuestion();
        $this->actingAs(User::factory()->admin()->create());
        $payload = $this->spamPayload(['title' => 'Edited title']);
        unset($payload['module_type']);

        $response = $this->put("/admin/questions/{$question->id}", $payload);

        $response->assertSessionHasErrors(['module_type' => 'The module type field is required.'])
            ->assertSessionMissing('success');
        $this->assertSame('Existing spam', $question->fresh()->title);
    }

    public function test_deleting_with_an_unknown_module_deletes_nothing(): void
    {
        $question = $this->spamQuestion();
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->delete("/admin/questions/{$question->id}", ['module' => 'quiz']);

        $response->assertSessionHasErrors(['module' => 'The selected module is invalid.'])
            ->assertSessionMissing('success');
        $this->assertModelExists($question);
    }

    /**
     * A spam scenario as the admin create and edit forms send it.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function spamPayload(array $overrides = []): array
    {
        return array_merge([
            'module_type' => 'spam',
            'type' => 'email',
            'key' => 'admin_spam',
            'title' => 'Prize email',
            'sender_name' => 'Prize Team',
            'sender_email' => 'win@prize.example',
            'subject' => 'You won!',
            'body' => 'Claim your prize now.',
            'opt1_text' => 'Flag as Junk',
            'opt1_result' => 'correct',
            'opt1_feedback' => 'Right, it is spam.',
            'opt2_text' => 'Allow to Inbox',
            'opt2_result' => 'incorrect',
            'opt2_feedback' => 'This is spam.',
        ], $overrides);
    }

    private function spamQuestion(): Question
    {
        return Question::create([
            'module' => 'spam',
            'type' => 'email',
            'key' => 'existing_spam',
            'title' => 'Existing spam',
            'sender_name' => 'Spammer',
            'body' => 'Buy now.',
            'options' => json_encode([]),
        ]);
    }
}
