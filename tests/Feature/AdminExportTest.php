<?php

namespace Tests\Feature;

use App\Models\SimulationAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_neutralises_spreadsheet_formulas_in_user_names(): void
    {
        $attacker = User::factory()->create(['name' => '=HYPERLINK("https://evil.example","Open")']);
        $student = User::factory()->create(['name' => 'Aisyah']);
        SimulationAttempt::create(['user_id' => $attacker->id, 'module' => 'spam', 'score' => 40]);
        SimulationAttempt::create(['user_id' => $student->id, 'module' => 'spam', 'score' => 90]);

        $csv = $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString(',Aisyah,', $csv);
    }
}
