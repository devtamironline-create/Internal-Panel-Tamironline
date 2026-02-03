<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Make team_id nullable for message tasks
            $table->foreignId('team_id')->nullable()->change();

            // Add message source fields
            $table->foreignId('message_id')->nullable()->after('parent_id')->constrained('messages')->onDelete('set null');
            $table->foreignId('conversation_id')->nullable()->after('message_id')->constrained('conversations')->onDelete('set null');
            $table->string('source')->default('manual')->after('conversation_id'); // manual, message
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropForeign(['conversation_id']);
            $table->dropColumn(['message_id', 'conversation_id', 'source']);
        });
    }
};
