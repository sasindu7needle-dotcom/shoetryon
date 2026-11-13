<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The files of a submission: the front photo (kept as the shoe's thumbnail)
     * and the GLB that n8n sends back from Tripo. Paths are relative to public/.
     */
    public function up(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('length_cm');
            $table->string('glb_path')->nullable()->after('photo_path');
            $table->string('tripo_task_id')->nullable()->after('glb_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'glb_path', 'tripo_task_id']);
        });
    }
};
