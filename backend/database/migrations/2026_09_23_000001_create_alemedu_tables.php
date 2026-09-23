<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('owner_id')->index();
            $table->string('title', 160);
            $table->string('organization')->default('');
            $table->string('region', 100)->default('');
            $table->string('category', 100)->default('');
            $table->enum('scope', ['institution', 'kazakhstan'])->default('kazakhstan');
            foreach (['context', 'users', 'materials', 'constraints', 'expected_outcome', 'success_criteria', 'contact'] as $field) {
                $table->text($field)->default('');
            }
            $table->json('confirmed_fields')->default('[]');
            $table->unsignedSmallInteger('score')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'score']);
            $table->index(['category', 'region', 'scope']);
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization');
            $table->json('interests');
            $table->json('skills');
            $table->json('technologies');
            $table->timestamps();
        });
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->text('idea');
            $table->text('plan');
            $table->string('timeline', 255);
            $table->string('prototype_link', 2048)->nullable();
            $table->enum('decision', ['pending', 'selected', 'rejected'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('tasks');
    }
};
