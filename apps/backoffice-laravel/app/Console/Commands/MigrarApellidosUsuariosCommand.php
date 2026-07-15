<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrarApellidosUsuariosCommand extends Command
{
    protected $signature = 'usuarios:migrar-apellidos {--dry-run : Solo muestra el reporte, sin escribir}';

    protected $description = 'Parte el campo last_name de users en apellido_paterno/apellido_materno mediante heurística (BL-045). Idempotente: solo procesa usuarios sin apellido_paterno aún.';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        if ($isDry) {
            $this->warn('[DRY-RUN] No se escribirá nada en la BD.');
        }

        $users = User::whereNull('apellido_paterno')
            ->whereNotNull('last_name')
            ->where('last_name', '!=', '')
            ->get();

        $migrated = 0;
        $ambiguous = [];

        foreach ($users as $user) {
            // last_name ahora es un accessor calculado (apellido_paterno+materno), aún vacíos en
            // este punto de la migración — hay que leer la columna cruda original, no la propiedad.
            $lastNameOriginal = $user->getRawOriginal('last_name');
            $words = preg_split('/\s+/', trim((string) $lastNameOriginal), -1, PREG_SPLIT_NO_EMPTY);
            $wordCount = count($words);

            if ($wordCount === 0) {
                continue;
            }

            if ($wordCount === 1) {
                $paterno = $words[0];
                $materno = null;
            } elseif ($wordCount === 2) {
                $paterno = $words[0];
                $materno = $words[1];
            } else {
                // 3+ palabras: mejor intento, pero se marca como ambiguo para revisión manual
                $materno = array_pop($words);
                $paterno = implode(' ', $words);
                $ambiguous[] = [
                    'id' => $user->id,
                    'nombre' => $user->first_name,
                    'last_name_original' => $lastNameOriginal,
                    'apellido_paterno_propuesto' => $paterno,
                    'apellido_materno_propuesto' => $materno,
                ];
            }

            $this->line("  {$user->id}: \"{$lastNameOriginal}\" → paterno=\"{$paterno}\" materno=\"" . ($materno ?? '') . '"' . ($wordCount >= 3 ? ' [AMBIGUO]' : ''));

            if (! $isDry) {
                $user->forceFill([
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
            $csv = "id,nombre,last_name_original,apellido_paterno_propuesto,apellido_materno_propuesto\n";
            foreach ($ambiguous as $row) {
                $csv .= implode(',', array_map(
                    fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                    $row
                )) . "\n";
            }

            $path = 'reportes/apellidos-usuarios-ambiguos-' . now()->format('Y-m-d_H-i') . '.csv';
            Storage::disk('local')->put($path, $csv);
            $this->warn('Reporte de casos ambiguos guardado en: storage/app/' . $path);
        }

        return self::SUCCESS;
    }
}
