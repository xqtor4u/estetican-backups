<?php

namespace App\Console\Commands;

use App\Models\ClinicalAttachment;
use App\Support\ClinicalAttachmentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoverAdjuntosClinicosPrivadosCommand extends Command
{
    protected $signature = 'clinico:mover-adjuntos-privados {--dry-run : Solo cuenta, sin mover}';

    protected $description = 'Mueve al disco privado los adjuntos clínicos que quedaron en el disco público (antes del 27/09/2026 se podían abrir por URL sin sesión). Idempotente.';

    public function handle(ClinicalAttachmentManager $manager): int
    {
        $total = ClinicalAttachment::count();

        if ($this->option('dry-run')) {
            $pending = ClinicalAttachment::all()->filter(fn ($a) => $a->file_path && Storage::disk('public')->exists($a->file_path))->count();
            $this->info("Adjuntos: {$total} · en disco público por mover: {$pending}");
            $this->warn('[DRY-RUN] No se movió nada.');

            return self::SUCCESS;
        }

        $moved = ClinicalAttachment::all()->filter(fn ($a) => $manager->moveToPrivate($a))->count();
        $this->info("Adjuntos: {$total} · movidos al disco privado: {$moved}");

        return self::SUCCESS;
    }
}
