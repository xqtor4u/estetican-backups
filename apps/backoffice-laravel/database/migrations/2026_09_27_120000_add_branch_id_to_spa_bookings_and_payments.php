<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B1 (27/09/2026): sucursal en citas y pagos — base para reportes y caja por sucursal, y para
 * que `{sucursal}` de una plantilla salga de la cita. Aditiva (columnas nullable); los datos
 * existentes se rellenan con la misma regla que BranchResolver, sin adivinar: lo que no se
 * pueda decidir queda null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('operator_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('client_id')->constrained('branches')->nullOnDelete();
        });

        $activeBranches = DB::table('branches')->where('is_active', true)->limit(2)->pluck('id');
        $onlyBranch = $activeBranches->count() === 1 ? $activeBranches->first() : null;

        // Citas: sucursal de quien la creó → la del operador (si tiene una sola) → la única activa.
        DB::table('spa_bookings')
            ->join('users', 'users.id', '=', 'spa_bookings.created_by_user_id')
            ->whereNull('spa_bookings.branch_id')
            ->whereNotNull('users.branch_id')
            ->update(['spa_bookings.branch_id' => DB::raw('users.branch_id')]);

        $singleBranchOperators = DB::table('operator_branch_assignments')
            ->select('operator_id', DB::raw('MIN(branch_id) as branch_id'))
            ->groupBy('operator_id')
            ->havingRaw('COUNT(DISTINCT branch_id) = 1')
            ->pluck('branch_id', 'operator_id');

        foreach ($singleBranchOperators as $operatorId => $branchId) {
            DB::table('spa_bookings')->whereNull('branch_id')->where('operator_id', $operatorId)->update(['branch_id' => $branchId]);
        }

        if ($onlyBranch) {
            DB::table('spa_bookings')->whereNull('branch_id')->update(['branch_id' => $onlyBranch]);
        }

        // Pagos: sucursal de su cita → la de quien cobró → la única activa.
        DB::table('payments')
            ->join('spa_bookings', 'spa_bookings.id', '=', 'payments.payable_id')
            ->whereIn('payments.payable_type', ['App\\Models\\SpaBooking', 'spa_booking'])
            ->whereNull('payments.branch_id')
            ->whereNotNull('spa_bookings.branch_id')
            ->update(['payments.branch_id' => DB::raw('spa_bookings.branch_id')]);

        DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.created_by_user_id')
            ->whereNull('payments.branch_id')
            ->whereNotNull('users.branch_id')
            ->update(['payments.branch_id' => DB::raw('users.branch_id')]);

        if ($onlyBranch) {
            DB::table('payments')->whereNull('branch_id')->update(['branch_id' => $onlyBranch]);
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
