<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('provider', 32);
            $table->string('provider_user_id', 255);

            $table->timestamps();

            $table->unique(
                ['provider', 'provider_user_id'],
                'social_accounts_provider_identity_unique'
            );

            $table->unique(
                ['user_id', 'provider'],
                'social_accounts_user_provider_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
