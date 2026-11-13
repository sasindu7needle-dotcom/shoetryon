<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who checked Tripo's 3D model against the photo, when, and what they
     * said: the passed checks when it was approved, the reason when rejected.
     */
    public function up(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('message')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->json('review_checks')->nullable()->after('reviewed_at');
            $table->text('review_note')->nullable()->after('review_checks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoe_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_checks', 'review_note']);
        });
    }
};
