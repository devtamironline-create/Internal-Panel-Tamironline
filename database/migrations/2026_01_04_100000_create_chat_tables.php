<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversations table
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['private', 'group'])->default('private');
            $table->string('name')->nullable(); // For group chats
            $table->string('avatar')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Conversation participants
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        // Messages table
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->enum('type', ['text', 'file', 'audio', 'image', 'system'])->default('text');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('file_size')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
        });

        // Message reads (for tracking who read the message)
        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['message_id', 'user_id']);
        });

        // Calls table
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('caller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['audio', 'video'])->default('audio');
            $table->enum('status', ['ringing', 'answered', 'ended', 'missed', 'rejected', 'busy'])->default('ringing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in seconds
            $table->timestamps();

            $table->index(['caller_id', 'created_at']);
            $table->index(['receiver_id', 'created_at']);
        });

        // User online status
        Schema::create('user_presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['online', 'away', 'busy', 'offline'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('current_page')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presences');
        Schema::dropIfExists('calls');
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
