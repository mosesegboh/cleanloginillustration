<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the signups table used by the landing-page registration flow.
     */
    public function up(): void
    {
        Schema::create('signups', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email')->unique();
            $table->char('country', 2);
            $table->string('country_code', 8);
            $table->string('phone_number', 20);
            $table->string('password');
            $table->timestamps();

            $table->index('country');
        });
    }

    /**
     * Drop the signups table.
     */
    public function down(): void
    {
        Schema::dropIfExists('signups');
    }
};
