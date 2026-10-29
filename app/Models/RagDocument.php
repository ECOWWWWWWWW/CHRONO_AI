<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RagDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';

    public const SOURCE_UPLOADED_FILE = 'uploaded_file';
    public const SOURCE_ADMIN_CURRICULUM = 'admin_curriculum';
    public const SOURCE_TEACHER_RESOURCE = 'teacher_resource';
    public const SOURCE_APPROVED_LESSON_PLAN = 'approved_lesson_plan';

    protected $fillable = [
        'source_type',
        'source_id',
        'title',
        'original_filename',
        'mime_type',
        'storage_path',
        'raw_text',
        'status',
        'error_message',
        'metadata',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany('App\\Models\\RagChunk');
    }

    public function isIndexed(): bool
    {
        return $this->status === self::STATUS_INDEXED;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
