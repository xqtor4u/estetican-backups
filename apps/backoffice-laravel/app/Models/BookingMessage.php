<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingMessage extends Model
{
    protected $fillable = [
        'spa_booking_id',
        'whatsapp_template_id',
        'channel',
        'trigger',
        'phone_number',
        'email_address',
        'message_body',
        'wa_link',
        'provider_message_id',
        'sent_by_user_id',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(SpaBooking::class, 'spa_booking_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
