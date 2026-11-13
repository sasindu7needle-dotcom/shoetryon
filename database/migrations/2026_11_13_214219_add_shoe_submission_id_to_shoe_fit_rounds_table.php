<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fit round can also be about a new shoe that is not in the library
     * yet: one sent from the feet preview, which changes the preview fit.
     */
    public function up(): void
    {
        Schema::table('shoe_fit_rounds', function (Blueprint $table) {
            $table->foreignId('shoe_id')->nullable()->change();
            $table->foreignId('shoe_submission_id')->nullable()->after('shoe_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_fit_rounds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shoe_submission_id');
        });
    }
};
