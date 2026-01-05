<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('blue');
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Team members pivot table
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member'); // member, lead
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        // Seed default teams
        DB::table('teams')->insert([
            [
                'name' => 'تیم فنی',
                'slug' => 'technical',
                'color' => 'blue',
                'icon' => 'wrench',
                'description' => 'تیم فنی و تعمیرات',
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'کال سنتر',
                'slug' => 'call-center',
                'color' => 'green',
                'icon' => 'phone',
                'description' => 'تیم پاسخگویی و پشتیبانی',
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'تیم مالی',
                'slug' => 'finance',
                'color' => 'purple',
                'icon' => 'currency',
                'description' => 'تیم حسابداری و مالی',
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user');
        Schema::dropIfExists('teams');
    }
};
