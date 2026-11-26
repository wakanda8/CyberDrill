<?php

namespace Tests\Feature;

use App\Models\MalwareScenario;
use App\Models\PhishingScenario;
use App\Models\Question;
use App\Models\SimulationAttempt;
use App\Models\SimulationProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_game_pages_do_not_contain_answers(): void
    {
        $phishing = $this->phishingScenario();
        $this->malwareScenario();
        $spam = $this->spamQuestion();

        $this->get('/phishing')->assertOk()
            ->assertSee('Urgent Wire Transfer')
            ->assertDontSee('SECRET-PHISHING-FEEDBACK')
            ->assertDontSee('malicious_zone')
            ->assertDontSee($phishing->key);

        $this->get('/malware')->assertOk()
            ->assertSee('Quarantine')
            ->assertDontSee('SECRET-MALWARE');

        $this->get('/spam')->assertOk()
            ->assertSee('Claim your prize')
            ->assertDontSee('SECRET-SPAM')
            ->assertDontSee($spam->key);
    }

    public function test_phishing_is_graded_on_the_server_and_ignores_a_forged_status(): void
    {
        $scenario = $this->phishingScenario();

        $this->postJson('/phishing/update', [
            'scenario_id' => $scenario->id,
            'action' => 'reply',
            'flags' => ['zone1' => 'green', 'zone2' => 'green', 'zone3' => 'green'],
            'status' => 'correct',
        ])->assertOk()
            ->assertJson(['status' => 'incorrect'])
            ->assertJsonPath('zones.*.correct', [false, false, true]);

        $this->assertDatabaseHas('simulation_progress', [
            'user_id' => $this->user->id, 'module' => 'phishing', 'scenario_id' => (string) $scenario->id, 'status' => 'incorrect',
        ]);
    }

    public function test_reporting_a_phishing_email_is_correct_with_a_zone_breakdown(): void
    {
        $scenario = $this->phishingScenario();

        $this->postJson('/phishing/update', [
            'scenario_id' => $scenario->id,
            'action' => 'report',
            'flags' => ['zone1' => 'red', 'zone2' => 'red', 'zone3' => 'green'],
        ])->assertOk()
            ->assertJson(['status' => 'correct', 'feedback' => 'SECRET-PHISHING-FEEDBACK'])
            ->assertJsonPath('zones.*.should_be', ['red', 'red', 'green'])
            ->assertJsonPath('zones.*.correct', [true, true, true]);
    }

    public function test_phishing_hint_is_generic_and_reveals_no_answer(): void
    {
        $scenario = $this->phishingScenario();

        $this->get('/phishing')->assertOk()->assertSee('Check each zone.');
        $this->getJson("/phishing/{$scenario->id}/hint")->assertNotFound();
    }

    public function test_spam_page_resumes_after_recorded_answers(): void
    {
        $answered = $this->spamQuestion();
        $this->spamQuestion();

        $this->postJson('/spam/update', ['question_id' => $answered->id, 'decision' => 'junk'])->assertOk();

        $this->get('/spam')->assertOk()->assertViewHas('savedProgress', [$answered->id => 'correct']);
    }

    public function test_the_first_answer_is_locked_in_every_module(): void
    {
        $phishing = $this->phishingScenario();
        $malware = $this->malwareScenario();
        $spam = $this->spamQuestion();

        $this->postJson('/phishing/update', ['scenario_id' => $phishing->id, 'action' => 'reply', 'flags' => []])->assertOk();
        $this->postJson('/phishing/update', ['scenario_id' => $phishing->id, 'action' => 'report', 'flags' => []])
            ->assertStatus(409)->assertJson(['status' => 'incorrect']);

        $this->postJson('/malware/update', ['question_id' => $malware->id, 'choice' => 0])->assertOk()->assertJson(['status' => 'incorrect']);
        $this->postJson('/malware/update', ['question_id' => $malware->id, 'choice' => 1])
            ->assertStatus(409)->assertJson(['status' => 'incorrect']);

        $this->postJson('/spam/update', ['question_id' => $spam->id, 'decision' => 'inbox'])->assertOk()->assertJson(['status' => 'incorrect']);
        $this->postJson('/spam/update', ['question_id' => $spam->id, 'decision' => 'junk'])
            ->assertStatus(409)->assertJson(['status' => 'incorrect']);
    }

    public function test_malware_and_spam_choices_are_graded_from_the_database(): void
    {
        $malware = $this->malwareScenario();
        $spam = $this->spamQuestion();

        $this->postJson('/malware/update', ['question_id' => $malware->id, 'choice' => 1, 'status' => 'incorrect'])
            ->assertOk()->assertJson(['status' => 'correct', 'feedback' => 'SECRET-MALWARE-RIGHT']);

        $this->postJson('/spam/update', ['question_id' => $spam->id, 'decision' => 'junk'])
            ->assertOk()->assertJson(['status' => 'correct', 'feedback' => 'SECRET-SPAM-RIGHT']);
    }

    public function test_unknown_scenarios_and_choices_are_rejected(): void
    {
        $malware = $this->malwareScenario();
        $notSpam = $this->spamQuestion(['module' => 'phishing']);

        $this->postJson('/phishing/update', ['scenario_id' => 999, 'action' => 'report'])->assertUnprocessable();
        $this->postJson('/malware/update', ['question_id' => 999, 'choice' => 0])->assertUnprocessable();
        $this->postJson('/malware/update', ['question_id' => $malware->id, 'choice' => 5])->assertUnprocessable();
        $this->postJson('/spam/update', ['question_id' => $notSpam->id, 'decision' => 'junk'])->assertUnprocessable();
    }

    public function test_scores_count_only_real_scenarios_and_cannot_exceed_100(): void
    {
        $answered = $this->malwareScenario();
        $this->malwareScenario();

        $this->postJson('/malware/update', ['question_id' => $answered->id, 'choice' => 1])->assertOk();
        foreach (['ghost-1', 'ghost-2', 'ghost-3'] as $fakeId) {
            SimulationProgress::create(['user_id' => $this->user->id, 'module' => 'malware', 'scenario_id' => $fakeId, 'status' => 'correct']);
        }

        $this->postJson('/malware/submit')->assertOk()->assertJson(['score' => 50]);

        $this->assertSame(50, SimulationAttempt::where('user_id', $this->user->id)->value('score'));
        $this->assertDatabaseMissing('simulation_progress', ['user_id' => $this->user->id, 'module' => 'malware']);
    }

    public function test_spam_submit_scores_the_recorded_answers(): void
    {
        $first = $this->spamQuestion();
        $this->spamQuestion();

        $this->postJson('/spam/update', ['question_id' => $first->id, 'decision' => 'junk'])->assertOk();

        $this->postJson('/spam/submit')->assertOk()->assertJson(['score' => 50]);
        $this->assertSame(50, SimulationAttempt::where('module', 'spam')->value('score'));
    }

    private function phishingScenario(): PhishingScenario
    {
        return PhishingScenario::create([
            'key' => fake()->unique()->slug(3),
            'title' => 'Urgent Wire Transfer',
            'type' => 'email',
            'sender_name' => 'John Smith - CEO',
            'sender_email' => 'ceo@company-exec.net',
            'subject' => 'URGENT: Client Payment Required',
            'body' => '<p>Please process this wire transfer now.</p>',
            'is_phishing' => true,
            'malicious_zone' => 'zone1, zone2',
            'feedback' => 'SECRET-PHISHING-FEEDBACK',
        ]);
    }

    private function malwareScenario(): MalwareScenario
    {
        return MalwareScenario::create([
            'title' => 'Suspicious PDF Invoice',
            'filename' => fake()->unique()->slug(2).'.pdf.exe',
            'filetype' => 'Application (.exe)',
            'publisher' => 'Unknown',
            'description' => 'Downloaded from an unpaid bill email.',
            'options' => json_encode([
                ['text' => 'Open', 'result' => 'incorrect', 'feedback' => 'SECRET-MALWARE-WRONG'],
                ['text' => 'Quarantine', 'result' => 'correct', 'feedback' => 'SECRET-MALWARE-RIGHT'],
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function spamQuestion(array $overrides = []): Question
    {
        return Question::create([
            'module' => 'spam',
            'type' => 'spam',
            'key' => fake()->unique()->slug(3),
            'title' => 'Lottery Win',
            'sender_name' => 'Prize Team',
            'sender_email' => 'win@prize.example',
            'subject' => 'Claim your prize',
            'body' => 'You have won. Reply with your bank details.',
            'options' => json_encode([
                ['text' => 'Flag as Junk', 'result' => 'correct', 'feedback' => 'SECRET-SPAM-RIGHT'],
                ['text' => 'Allow to Inbox', 'result' => 'incorrect', 'feedback' => 'SECRET-SPAM-WRONG'],
            ]),
            ...$overrides,
        ]);
    }
}
