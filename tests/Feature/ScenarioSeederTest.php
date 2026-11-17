<?php

namespace Tests\Feature;

use App\Models\MalwareScenario;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_malware_keeps_admin_scenarios_and_ids(): void
    {
        $this->seed(QuestionSeeder::class);
        $builtInIds = MalwareScenario::orderBy('id')->pluck('id')->all();

        $custom = MalwareScenario::create([
            'title' => 'Admin-added scenario',
            'filename' => 'custom_tool.exe',
            'filetype' => 'Application (.exe)',
            'publisher' => 'Unknown',
            'description' => 'Added through the admin page.',
            'options' => json_encode([]),
        ]);

        $this->seed(QuestionSeeder::class);

        $this->assertCount(8, $builtInIds);
        $this->assertSame(9, MalwareScenario::count());
        $this->assertModelExists($custom);
        $this->assertSame($builtInIds, MalwareScenario::whereKeyNot($custom->id)->orderBy('id')->pluck('id')->all());
    }
}
