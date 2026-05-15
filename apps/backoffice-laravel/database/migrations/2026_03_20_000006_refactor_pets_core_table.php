<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('breed');
            $table->string('microchip_code')->nullable()->after('birth_date');
            $table->string('tattoo_code')->nullable()->after('microchip_code');
            $table->string('sex')->nullable()->after('tattoo_code');
            $table->string('coat_color')->nullable()->after('sex');
            $table->string('size')->nullable()->after('coat_color');
            $table->boolean('is_sterilized')->default(false)->after('size');
            $table->text('notes')->nullable()->after('is_sterilized');
        });

        $pets = DB::table('pets')->select('id', 'medical_alerts', 'temperament', 'weight')->get();

        foreach ($pets as $pet) {
            $notes = [];

            if ($pet->temperament) {
                $notes[] = 'Temperamento historico: ' . $pet->temperament;
            }

            if ($pet->weight !== null) {
                $notes[] = 'Peso historico registrado: ' . $pet->weight . ' kg';
            }

            if (!empty($notes)) {
                DB::table('pets')->where('id', $pet->id)->update([
                    'notes' => implode("\n", $notes),
                ]);
            }

            if ($pet->medical_alerts) {
                $alerts = json_decode($pet->medical_alerts, true);

                if (is_array($alerts)) {
                    foreach ($alerts as $alert) {
                        if (!is_string($alert) || trim($alert) === '') {
                            continue;
                        }

                        DB::table('pet_medical_alerts')->insert([
                            'pet_id' => $pet->id,
                            'category' => 'general',
                            'description' => trim($alert),
                            'severity' => null,
                            'notes' => 'Migrado desde pets.medical_alerts',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn(['weight', 'temperament', 'medical_alerts']);
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->nullable()->after('breed');
            $table->string('temperament')->nullable()->after('weight');
            $table->json('medical_alerts')->nullable()->after('temperament');
        });

        Schema::table('pets', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'microchip_code',
                'tattoo_code',
                'sex',
                'coat_color',
                'size',
                'is_sterilized',
                'notes',
            ]);
        });
    }
};
