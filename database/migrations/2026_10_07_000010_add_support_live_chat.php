<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('category', 30)->default('other');
            $table->uuid('client_message_id')->nullable();
            $table->unique(['user_id', 'client_message_id']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->uuid('client_message_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unique(['support_ticket_id', 'sender_user_id', 'client_message_id'], 'support_message_retry_unique');
            $table->index(['support_ticket_id', 'id']);
            $table->index(['support_ticket_id', 'sender_type', 'read_at'], 'support_message_unread_index');
        });

        Schema::create('support_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 50);
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_message_attachments');
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropUnique('support_message_retry_unique');
            $table->dropIndex(['support_ticket_id', 'id']);
            $table->dropIndex('support_message_unread_index');
            $table->dropColumn(['client_message_id', 'read_at']);
        });
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_message_id']);
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropColumn(['category', 'client_message_id']);
        });
    }
};
