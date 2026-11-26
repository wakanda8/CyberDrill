<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One answer per user, module and scenario, so two answers sent at the same time
     * can't both be stored.
     */
    public function up(): void
    {
        // simulation_progress only holds unsubmitted answers, and every answer saved before
        // this release was graded by the browser, so none can be trusted. Clear them (this
        // also removes any duplicates). Submitted scores in simulation_attempts are kept.
        DB::table('simulation_progress')->delete();

        Schema::table('simulation_progress', function (Blueprint $table) {
            $table->unique(['user_id', 'module', 'scenario_id']);
        });
    }

    /**
     * Drops the index. Answers cleared by up() are not restored.
     */
    public function down(): void
    {
        Schema::table('simulation_progress', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'module', 'scenario_id']);
        });
    }
};
