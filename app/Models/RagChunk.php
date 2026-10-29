<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'rag_document_id',
        'chunk_index',
        'content',
        'character_count',
        'point_id',
        'metadata',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RagDocument::class, 'rag_document_id');
    }
}
