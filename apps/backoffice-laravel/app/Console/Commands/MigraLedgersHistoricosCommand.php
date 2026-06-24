<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigraLedgersHistoricosCommand extends Command
{
    protected $signature = 'finanzas:migrar-ledgers-historicos {--dry-run : Solo muestra qué haría, sin escribir}';

    protected $description = 'Crea asientos contables (JE) para registros históricos de cash_ledgers y bank_ledgers que no tienen póliza aún.';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        $caja    = Account::where('code', '1100')->first();
        $banco   = Account::where('code', '1210')->first();
        $ingreso = Account::where('code', '4900')->first();

        if (! $caja || ! $ingreso) {
            $this->error('Faltan cuentas contables 1100 (Caja) o 4900 (Otros ingresos). Ejecuta el seeder de cuentas.');
            return self::FAILURE;
        }

        // Usuario admin para created_by
        $admin = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
            ->first();

        if (! $admin) {
            $this->error('No se encontró ningún usuario con rol admin.');
            return self::FAILURE;
        }

        if ($isDry) {
            $this->warn('[DRY-RUN] No se escribirá nada en la BD.');
        }

        $migrated = 0;
        $skipped  = 0;

        // ---- cash_ledgers ----
        $this->info('Procesando cash_ledgers...');

        CashLedger::chunk(200, function ($records) use (
            $caja, $ingreso, $admin, $isDry, &$migrated, &$skipped
        ) {
            foreach ($records as $ledger) {
                $exists = JournalEntry::where('reference_type', CashLedger::class)
                    ->where('reference_id', $ledger->id)
                    ->exists();

                if ($exists) {
                    $this->line("  SKIP CashLedger #{$ledger->id} — ya tiene póliza");
                    $skipped++;
                    continue;
                }

                $this->line("  MIGRAR CashLedger #{$ledger->id}: \${$ledger->amount} ({$ledger->category}) — {$ledger->created_at}");

                if (! $isDry) {
                    $this->crearAsiento(
                        ledgerId:    $ledger->id,
                        ledgerClass: CashLedger::class,
                        amount:      (float) $ledger->amount,
                        entryDate:   $ledger->created_at->toDateString(),
                        description: "Ingreso efectivo histórico #{$ledger->id} — {$ledger->category}",
                        debitAccount:  $ingreso,
                        creditAccount: $caja,
                        adminId:     $admin->id,
                        notes:       $ledger->notes,
                    );
                }

                $migrated++;
            }
        });

        // ---- bank_ledgers ----
        $this->info('Procesando bank_ledgers...');

        $fallbackBanco = $banco ?? $ingreso;

        CashLedger::getQuery(); // reset

        BankLedger::chunk(200, function ($records) use (
            $ingreso, $fallbackBanco, $admin, $isDry, &$migrated, &$skipped
        ) {
            foreach ($records as $ledger) {
                $exists = JournalEntry::where('reference_type', BankLedger::class)
                    ->where('reference_id', $ledger->id)
                    ->exists();

                if ($exists) {
                    $this->line("  SKIP BankLedger #{$ledger->id} — ya tiene póliza");
                    $skipped++;
                    continue;
                }

                // Buscar cuenta de banco por método de pago; fallback = 1210
                $creditAccount = $this->resolverCuentaBanco($ledger->payment_method) ?? $fallbackBanco;

                $this->line("  MIGRAR BankLedger #{$ledger->id}: \${$ledger->amount} vía {$ledger->payment_method} → cuenta {$creditAccount->code} {$creditAccount->name}");

                if (! $isDry) {
                    $this->crearAsiento(
                        ledgerId:    $ledger->id,
                        ledgerClass: BankLedger::class,
                        amount:      (float) $ledger->amount,
                        entryDate:   $ledger->created_at->toDateString(),
                        description: "Ingreso banco histórico #{$ledger->id} — {$ledger->payment_method}",
                        debitAccount:  $ingreso,
                        creditAccount: $creditAccount,
                        adminId:     $admin->id,
                        notes:       $ledger->notes,
                    );
                }

                $migrated++;
            }
        });

        $label = $isDry ? 'A migrar' : 'Migrados';
        $this->newLine();
        $this->info("$label: $migrated | Ya tenían póliza (skip): $skipped");

        return self::SUCCESS;
    }

    private function crearAsiento(
        int    $ledgerId,
        string $ledgerClass,
        float  $amount,
        string $entryDate,
        string $description,
        Account $debitAccount,
        Account $creditAccount,
        int    $adminId,
        ?string $notes,
    ): void {
        DB::transaction(function () use (
            $ledgerId, $ledgerClass, $amount, $entryDate, $description,
            $debitAccount, $creditAccount, $adminId, $notes
        ) {
            $entry = JournalEntry::create([
                'entry_date'          => $entryDate,
                'description'         => $description,
                'status'              => 'aplicado',
                'created_by_user_id'  => $adminId,
                'posted_by_user_id'   => $adminId,
                'posted_at'           => now(),
                'reference_type'      => $ledgerClass,
                'reference_id'        => $ledgerId,
                'notes'               => $notes ?? 'Migrado desde sistema legado (BL-021)',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $debitAccount->id,
                'debit'            => $amount,
                'credit'           => 0,
                'description'      => 'Ingreso por servicios (legado)',
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $creditAccount->id,
                'debit'            => 0,
                'credit'           => $amount,
                'description'      => 'Cobro recibido (legado)',
            ]);
        });
    }

    private function resolverCuentaBanco(string $paymentMethodName): ?Account
    {
        $method = PaymentMethod::whereRaw('LOWER(name) = ?', [strtolower($paymentMethodName)])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($paymentMethodName) . '%'])
            ->whereNotNull('account_id')
            ->first();

        return $method?->account;
    }
}
