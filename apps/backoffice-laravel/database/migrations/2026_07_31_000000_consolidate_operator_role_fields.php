<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolida el rol del operador en una sola fuente de verdad: `operator_role_assignments`
 * (checkboxes "Tipos de operador", ya existente desde 2026_03_22). Antes de esta migración
 * había dos campos más en `operators` — `role` (texto legado, auto-derivado del primer tipo
 * marcado) y `operator_role_id` (FK única agregada después, huérfana: el formulario actual
 * nunca la vuelve a tocar) — que podían mostrar valores distintos entre sí y distintos de los
 * tipos realmente asignados. Ver BITACORA 31/07/2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Backfill: cualquier operador con operator_role_id pero sin ninguna fila en el
        // m2m (nunca editado con los checkboxes) recibe una asignación equivalente, para
        // no perder el único dato real que tenía antes de quitar la columna.
        DB::table('operators')
            ->whereNotNull('operator_role_id')
            ->whereNotIn('id', function ($query) {
                $query->select('operator_id')->from('operator_role_assignments');
            })
            ->get(['id', 'operator_role_id'])
            ->each(function ($operator) {
                DB::table('operator_role_assignments')->insert([
                    'operator_id' => $operator->id,
                    'operator_role_id' => $operator->operator_role_id,
                    'is_primary' => true,
                    'starts_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('operators', function (Blueprint $table) {
            $table->dropForeign(['operator_role_id']);
            $table->dropColumn(['operator_role_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('operators', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->foreignId('operator_role_id')->nullable()->after('role')->constrained('operator_roles')->nullOnDelete();
        });

        // Reconstruir ambos campos legado a partir del tipo marcado como principal.
        DB::table('operator_role_assignments')
            ->where('is_primary', true)
            ->get(['operator_id', 'operator_role_id'])
            ->each(function ($assignment) {
                $roleName = DB::table('operator_roles')->where('id', $assignment->operator_role_id)->value('name');

                DB::table('operators')->where('id', $assignment->operator_id)->update([
                    'operator_role_id' => $assignment->operator_role_id,
                    'role' => $roleName,
                ]);
            });
    }
};
