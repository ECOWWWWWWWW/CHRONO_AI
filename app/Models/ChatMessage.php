<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $table = 'chrono_chat_messages';

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'input_tokens',
        'output_tokens',
        'retrieved_chunks_meta',
    ];

    protected $casts = [
        'retrieved_chunks_meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }
}
