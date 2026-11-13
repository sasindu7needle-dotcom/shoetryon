<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New shoes are worn as their GLB on the tracked feet, so they need no
     * DeepAR effect. The leg mask hides the part of the shoe that the
     * wearer's ankle would cover.
     */
    public function up(): void
    {
        Schema::table('shoes', function (Blueprint $table) {
            $table->string('effect')->nullable()->change();
            $table->boolean('leg_mask')->default(false)->after('fit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shoes', function (Blueprint $table) {
            $table->dropColumn('leg_mask');
            $table->string('effect')->nullable(false)->change();
        });
    }
};
