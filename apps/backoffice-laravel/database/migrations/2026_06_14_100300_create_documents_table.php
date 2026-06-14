<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_series_id')->constrained()->restrictOnDelete();
            $table->string('document_type', 30);     // recibo, factura, sin_documento
            $table->unsignedInteger('folio_number'); // número puro, para ordenar/buscar
            $table->string('folio_display', 30);     // formateado: R-0001
            $table->string('status', 20)->default('emitido'); // borrador, emitido, cancelado

            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->string('email_to')->nullable();
            $table->timestamp('email_sent_at')->nullable();

            // Hooks para integraciones futuras
            $table->string('fiscal_uuid', 100)->nullable();     // CFDI/SAT timbrado
            $table->string('gateway_reference', 100)->nullable(); // pasarela de pago

            $table->nullableMorphs('documentable'); // spa_booking, hotel_reservation, etc.

            $table->timestamps();

            $table->index(['document_type', 'status']);
            $table->index(['document_series_id', 'folio_number']);
            $table->unique(['document_series_id', 'folio_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
