<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the admin lined the GLB up on their feet in the preview. It becomes
     * the shoe's starting fit when the model is approved.
     */
    public function up(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->json('preview_fit')->nullable()->after('glb_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->dropColumn('preview_fit');
        });
    }
};
