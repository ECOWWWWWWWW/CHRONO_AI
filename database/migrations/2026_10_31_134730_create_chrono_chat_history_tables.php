<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sessions Table (Represents a specific lesson context)
        Schema::create('chrono_chat_sessions', function (Blueprint $table) {
            $table->id();

            // Link to Chrono's local User model for now. 
            // In Chronica, this will link to App\Models\User.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title'); // e.g., "Grade 7 Science: Water Cycle"
            $table->string('subject')->nullable();
            $table->string('grade_level')->nullable();
            $table->text('description')->nullable(); // Initial prompt/goal

            $table->string('status')->default('active'); // active, archived

            // Optional: Store RAG metadata filters here if needed per session
            $table->json('rag_metadata')->nullable();

            $table->timestamps();
        });

        // 2. Messages Table (The actual chat turns)
        Schema::create('chrono_chat_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('chrono_chat_sessions')
                ->cascadeOnDelete();

            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');

            // Token tracking for cost analysis
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();

            // Debugging: What RAG chunks were used?
            $table->json('retrieved_chunks_meta')->nullable();

            $table->timestamps();

            // Index for fast history retrieval
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chrono_chat_messages');
        Schema::dropIfExists('chrono_chat_sessions');
    }
};
