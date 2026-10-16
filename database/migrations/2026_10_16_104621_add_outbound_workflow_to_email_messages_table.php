<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('status', 32)
                ->nullable()
                ->index();

            $table->json('recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->json('bcc_recipients')->nullable();

            $table->timestamp('draft_saved_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamp('sending_started_at')->nullable();
            $table->unsignedInteger('send_attempts')->default(0);

            $table->uuid('sending_token')->nullable()->unique();
            $table->text('last_send_error')->nullable();
        });

        DB::table('email_messages')
            ->where('direction', 'inbound')
            ->whereNull('status')
            ->update(['status' => 'received']);

        DB::table('email_messages')
            ->where('direction', 'outbound')
            ->whereNotNull('sent_at')
            ->whereNull('status')
            ->update(['status' => 'sent']);

        DB::table('email_messages')
            ->where('direction', 'outbound')
            ->whereNull('sent_at')
            ->whereNull('status')
            ->update(['status' => 'unknown']);
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropUnique(['sending_token']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'status',
                'recipients',
                'cc_recipients',
                'bcc_recipients',
                'draft_saved_at',
                'failed_at',
                'sending_started_at',
                'send_attempts',
                'sending_token',
                'last_send_error',
            ]);
        });
    }
};
