<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_thread_id')
                ->constrained('email_threads')
                ->cascadeOnDelete();

            $table->string('direction');
            $table->string('source')->default('resend');

            $table->string('message_id')->nullable()->unique();
            $table->string('in_reply_to')->nullable();

            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->string('to_email');

            $table->string('subject')->nullable();

            $table->longText('text_body')->nullable();
            $table->longText('html_body')->nullable();

            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            $table->boolean('is_read')->default(false);

            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['email_thread_id', 'created_at']);
            $table->index(['direction', 'is_read']);
            $table->index('in_reply_to');
            $table->index('from_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
