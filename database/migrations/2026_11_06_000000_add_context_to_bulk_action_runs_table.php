<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('bulk-actions.table', 'bulk_action_runs'), function (Blueprint $table) {
            $table->json('context')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table(config('bulk-actions.table', 'bulk_action_runs'), function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
