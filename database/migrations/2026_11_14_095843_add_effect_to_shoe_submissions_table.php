<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The DeepAR effect built from Tripo's GLB (public/effects/generated), so
     * DeepAR draws the shoe on the feet it tracks, and why it could not be
     * built when it failed.
     */
    public function up(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->string('effect_path')->nullable()->after('glb_path');
            $table->text('effect_error')->nullable()->after('effect_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->dropColumn(['effect_path', 'effect_error']);
        });
    }
};
