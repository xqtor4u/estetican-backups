<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAiChat extends Model
{
    protected $fillable = [
        'session_uuid',
        'message_count',
    ];

    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ServiceAiMessage::class);
    }
}
