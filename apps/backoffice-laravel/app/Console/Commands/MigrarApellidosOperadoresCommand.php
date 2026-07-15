<?php

namespace App\Console\Commands;

use App\Models\Operator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrarApellidosOperadoresCommand extends Command
{
    protected $signature = 'operadores:migrar-apellidos {--dry-run : Solo muestra el reporte, sin escribir}';

    protected $description = 'Parte el campo full_name de operators en first_name/apellido_paterno/apellido_materno mediante heurística (BL-045b). A diferencia de clients/users, full_name mezcla nombre(s) + apellidos en un solo string. Idempotente: solo procesa operadores sin first_name aún.';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        if ($isDry) {
            $this->warn('[DRY-RUN] No se escribirá nada en la BD.');
        }

        $operators = Operator::whereNull('first_name')
            ->whereNotNull('full_name')
            ->where('full_name', '!=', '')
            ->get();

        $migrated = 0;
        $ambiguous = [];

        foreach ($operators as $operator) {
            // full_name ahora es un accessor calculado (first_name+apellidos), aún vacío en
            // este punto de la migración — hay que leer la columna cruda original, no la propiedad.
            $fullNameOriginal = $operator->getRawOriginal('full_name');
            $words = preg_split('/\s+/', trim((string) $fullNameOriginal), -1, PREG_SPLIT_NO_EMPTY);
            $wordCount = count($words);

            if ($wordCount === 0) {
                continue;
            }

            if ($wordCount === 1) {
                // Solo un nombre de pila, sin apellidos capturables.
                $firstName = $words[0];
                $paterno = null;
                $materno = null;
            } elseif ($wordCount === 2) {
                // Caso más común para un nombre corto: "Nombre Apellido" (sin materno).
                $firstName = $words[0];
                $paterno = $words[1];
                $materno = null;
            } else {
                // 3+ palabras: las últimas 2 son los apellidos, el resto es el/los nombre(s).
                $materno = array_pop($words);
                $paterno = array_pop($words);
                $firstName = implode(' ', $words);

                if ($wordCount >= 5) {
                    // 5+ palabras es inusual (posibles apellidos compuestos, ej. "de la Cruz") —
                    // se aplica el mismo mejor-intento pero se marca para revisión manual.
                    $ambiguous[] = [
                        'id' => $operator->id,
                        'code' => $operator->code,
                        'full_name_original' => $fullNameOriginal,
                        'first_name_propuesto' => $firstName,
                        'apellido_paterno_propuesto' => $paterno,
                        'apellido_materno_propuesto' => $materno,
                    ];
                }
            }

            $this->line("  {$operator->id} ({$operator->code}): \"{$fullNameOriginal}\" → nombre=\"{$firstName}\" paterno=\"" . ($paterno ?? '') . '" materno="' . ($materno ?? '') . '"' . ($wordCount >= 5 ? ' [AMBIGUO]' : ''));

            if (! $isDry) {
                $operator->forceFill([
                    'first_name' => $firstName,
                    'apellido_paterno' => $paterno,
                    'apellido_materno' => $materno,
                ])->save();
            }

            $migrated++;
        }

        $label = $isDry ? 'A migrar' : 'Migrados';
        $this->newLine();
        $this->info("$label: $migrated | Ambiguos (revisar manualmente): " . count($ambiguous));

        if (! $isDry && count($ambiguous) > 0) {
            $csv = "id,code,full_name_original,first_name_propuesto,apellido_paterno_propuesto,apellido_materno_propuesto\n";
            foreach ($ambiguous as $row) {
                $csv .= implode(',', array_map(
                    fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                    $row
                )) . "\n";
            }

            $path = 'reportes/apellidos-operadores-ambiguos-' . now()->format('Y-m-d_H-i') . '.csv';
            Storage::disk('local')->put($path, $csv);
            $this->warn('Reporte de casos ambiguos guardado en: storage/app/' . $path);
        }

        return self::SUCCESS;
    }
}
