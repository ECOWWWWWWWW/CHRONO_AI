<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->id();

            $table->string('source_type')->index();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('storage_path')->nullable();

            $table->longText('raw_text')->nullable();

            $table->string('status')->default('pending')->index();
            $table->text('error_message')->nullable();

            $table->json('metadata')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rag_document_id')
                ->constrained('rag_documents')
                ->cascadeOnDelete();

            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->unsignedInteger('character_count')->default(0);

            $table->uuid('point_id')->unique();

            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();

            $table->timestamps();

            $table->unique(['rag_document_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
        Schema::dropIfExists('rag_documents');
    }
};
