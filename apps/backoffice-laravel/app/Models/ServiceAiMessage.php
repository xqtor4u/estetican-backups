<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAiMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'service_ai_chat_id',
        'role',
        'content',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ServiceAiChat::class, 'service_ai_chat_id');
    }
}
