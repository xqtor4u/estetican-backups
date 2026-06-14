<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'description', 'is_active', 'allows_entries',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'allows_entries' => 'boolean',
    ];

    // Tipos válidos de cuenta
    const TYPES = ['activo', 'pasivo', 'capital', 'ingreso', 'gasto'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    // Saldo de la cuenta: débitos - créditos (para activos/gastos) o créditos - débitos (pasivos/ingresos/capital)
    public function balance(): float
    {
        $lines = $this->journalEntryLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'aplicado'))
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();

        $debit  = (float) ($lines->total_debit ?? 0);
        $credit = (float) ($lines->total_credit ?? 0);

        return in_array($this->type, ['activo', 'gasto'])
            ? $debit - $credit
            : $credit - $debit;
    }
}
