<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which foot the photographed shoe is for (left or right), as the admin
     * chose it. Shoes sent before this have none, and their side is still
     * guessed from the model's shape.
     */
    public function up(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->string('shoe_side', 10)->nullable()->after('shoe_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->dropColumn('shoe_side');
        });
    }
};
