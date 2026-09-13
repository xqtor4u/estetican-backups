<?php

namespace App\Console\Commands;

use App\Models\BankLedger;
use App\Models\CashLedger;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * SYNC-098 — porteo a producción, paso 1 de 2: `payments` pasa a ser la tabla única
 * canónica de cobro, pero `cash_ledgers`/`bank_ledgers` tienen filas reales históricas
 * (a diferencia de `tst`, donde ambas están en 0). Sin este backfill, esas filas
 * desaparecerían de todo reporte que ahora lee solo `payments` (Dashboard, cortes de
 * caja, `SpaBooking::totalPaid()`, etc.) — el dinero seguiría en la BD pero seria
 * invisible para el negocio.
 *
 * Crea un `Payment` por cada fila de `cash_ledgers`/`bank_ledgers` que todavía no tenga
 * uno — idempotente: marca cada `Payment` creado con un prefijo reconocible al inicio de
 * `notes` (`[LEDGER_BACKFILL {tabla}#{id}]`) y no vuelve a crear uno si ya existe. Sin
 * migración de esquema — no toca ni borra `cash_ledgers`/`bank_ledgers` (paso 2, aparte,
 * cuando se confirme el backfill contra los reportes reales).
 */
class BackfillPaymentsFromLedgersCommand extends Command
{
    protected $signature = 'payments:backfill-ledgers {--dry-run : Solo muestra qué haría, sin escribir}';

    protected $description = 'SYNC-098: crea un Payment por cada fila histórica de cash_ledgers/bank_ledgers, para que payments quede como fuente única sin perder dinero histórico.';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        if ($isDry) {
            $this->warn('[DRY-RUN] No se escribirá nada en la BD.');
        }

        $created = 0;
        $skipped = 0;

        $this->info('Procesando cash_ledgers (destino: caja)...');
        [$c, $s] = $this->migrateTable(CashLedger::class, 'cash_ledgers', 'caja', $isDry);
        $created += $c;
        $skipped += $s;

        $this->info('Procesando bank_ledgers (destino: banco)...');
        [$c, $s] = $this->migrateTable(BankLedger::class, 'bank_ledgers', 'banco', $isDry);
        $created += $c;
        $skipped += $s;

        $label = $isDry ? 'A crear' : 'Creados';
        $this->newLine();
        $this->info("$label: $created | Ya migrados (skip): $skipped");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $ledgerClass
     * @return array{0: int, 1: int} [creados, saltados]
     */
    private function migrateTable(string $ledgerClass, string $table, string $destination, bool $isDry): array
    {
        $created = 0;
        $skipped = 0;

        $ledgerClass::orderBy('id')->chunk(200, function ($records) use ($table, $destination, $isDry, &$created, &$skipped) {
            foreach ($records as $ledger) {
                $marker = "[LEDGER_BACKFILL {$table}#{$ledger->id}]";

                if (Payment::where('notes', 'like', $marker.'%')->exists()) {
                    $this->line("  SKIP {$table}#{$ledger->id} — ya tiene Payment de backfill");
                    $skipped++;

                    continue;
                }

                $this->line("  MIGRAR {$table}#{$ledger->id}: \${$ledger->amount} ({$ledger->category}) — {$ledger->created_at}");

                if (! $isDry) {
                    $notes = trim($marker.' '.($ledger->notes ?? ''));

                    $payment = Payment::create([
                        'client_id' => $ledger->client_id,
                        'payable_type' => $ledger->payable_type,
                        'payable_id' => $ledger->payable_id,
                        'document_id' => $ledger->document_id,
                        'amount' => $ledger->amount,
                        'processing_fee' => $ledger->processing_fee ?? 0,
                        'payment_method' => $ledger->payment_method,
                        'destination' => $destination,
                        'external_reference' => $ledger->external_reference ?? null,
                        'category' => $ledger->category,
                        'notes' => $notes,
                        'cleared_at' => $ledger->cleared_at ?? null,
                        'created_by_user_id' => $ledger->created_by_user_id,
                    ]);

                    // Preserva la fecha real del cobro histórico — sin esto, todo quedaría
                    // fechado "hoy" y desaparecería de los reportes de días/meses anteriores.
                    $payment->forceFill([
                        'created_at' => $ledger->created_at,
                        'updated_at' => $ledger->updated_at ?? $ledger->created_at,
                    ])->save();
                }

                $created++;
            }
        });

        return [$created, $skipped];
    }
}
