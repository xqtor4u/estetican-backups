<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_type')->nullable()->after('cancelled_by_user_id')->comment('correction (el dinero se queda donde está) o refund (reversión real de caja/banco)');
            $table->text('cancellation_reason')->nullable()->after('cancellation_type');
            $table->foreignId('supersedes_document_id')->nullable()->after('cancellation_reason')->constrained('documents')->nullOnDelete();
            $table->json('line_items_snapshot')->nullable()->after('total')->comment('Snapshot congelado al emitir: nombre de servicio/artículo, operador, cantidad, precio, costo externo, notas — sobrevive aunque el catálogo cambie después');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('payable_id')->constrained('documents')->nullOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->foreignId('cancelled_by_user_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropColumn(['cancelled_at', 'cancelled_by_user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->dropColumn('document_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropForeign(['supersedes_document_id']);
            $table->dropColumn([
                'cancelled_at', 'cancelled_by_user_id', 'cancellation_type',
                'cancellation_reason', 'supersedes_document_id', 'line_items_snapshot',
            ]);
        });
    }
};
