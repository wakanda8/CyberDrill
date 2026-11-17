<?php

namespace Tests\Feature;

use App\Models\SimulationAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_PAGES = ['/admin', '/admin/export', '/admin/questions'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (self::ADMIN_PAGES as $page) {
            $this->get($page)->assertRedirect('/login');
        }
    }

    public function test_normal_users_cannot_reach_any_admin_route(): void
    {
        $user = User::factory()->create();
        $attempt = SimulationAttempt::create(['user_id' => $user->id, 'module' => 'spam', 'score' => 50]);

        $this->actingAs($user);

        foreach (self::ADMIN_PAGES as $page) {
            $this->get($page)->assertForbidden();
        }
        $this->get('/admin/questions/create')->assertForbidden();
        $this->get('/questions')->assertForbidden();
        $this->post('/questions', ['module_type' => 'spam'])->assertForbidden();
        $this->delete("/admin/attempt/{$attempt->id}")->assertForbidden();

        $this->assertDatabaseHas('simulation_attempts', ['id' => $attempt->id]);
    }

    public function test_admins_can_use_admin_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (self::ADMIN_PAGES as $page) {
            $this->get($page)->assertOk();
        }
    }

    public function test_repair_all_route_is_gone(): void
    {
        $this->get('/repair-all')->assertNotFound();
    }

    public function test_question_show_urls_are_not_routed(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin/questions/1')->assertMethodNotAllowed();
        $this->get('/questions/1')->assertMethodNotAllowed();
    }

    public function test_make_admin_command_promotes_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'Student@Example.com']);

        $this->artisan('app:make-admin', ['email' => 'student@example.com'])->assertExitCode(0);
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('app:make-admin', ['email' => 'nobody@example.com'])->assertExitCode(1);
    }
}
