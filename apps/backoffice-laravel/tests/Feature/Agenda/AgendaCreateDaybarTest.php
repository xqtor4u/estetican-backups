<?php

namespace Tests\Feature\Agenda;

use App\Models\Client;
use App\Models\Pet;
use App\Support\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

/**
 * AgSpaCre — la barra de disponibilidad del operador (`dayTimeline`) del panel de
 * "Nueva Reservación". Se pidió que muestre marcas de hora en punto (no solo inicio/fin),
 * una leyenda de colores (verde = disponible, rojo = ocupado, línea azul = hora elegida)
 * y la hora elegida legible bajo el marcador. La barra la pinta JS en el cliente; acá solo
 * se verifica que la página sirve el script con esas piezas.
 */
class AgendaCreateDaybarTest extends TestCase
{
    use CreatesAdminUser;
    use RefreshDatabase;

    private function createPage(): TestResponse
    {
        $client = Client::create(['first_name' => 'Ana', 'apellido_paterno' => 'Ruiz']);
        $pet = Pet::create(['client_id' => $client->id, 'name' => 'Luka']);

        return $this->actingAs($this->createAdminUser())
            ->get(route('pets.bookings.create', $pet));
    }

    public function test_page_renders(): void
    {
        $this->createPage()->assertOk()->assertSee('Nueva Reservación');
    }

    public function test_services_block_is_rendered_before_the_scheduling_block(): void
    {
        // El operador se elige por servicio; la fecha/hora arranca bloqueada hasta que haya
        // uno. Por eso el bloque de servicios debe ir primero — antes obligaba a bajar a
        // servicios y volver a subir a la fecha.
        $this->createPage()->assertSeeInOrder([
            '1 · Selección de servicios',
            '2 · Fecha, jaula y notas',
            'for="scheduled_at"',
        ], false);
    }

    public function test_daybar_ships_a_colour_legend(): void
    {
        $res = $this->createPage();

        $res->assertSee('agenda-daybar-legend', false);
        $res->assertSee('>Disponible<', false);
        $res->assertSee('>Ocupado<', false);
        $res->assertSee('Hora elegida', false);
    }

    public function test_daybar_draws_hourly_ticks_and_an_axis(): void
    {
        $res = $this->createPage();

        // El bucle que dibuja una marca + etiqueta por cada hora en punto del rango.
        $res->assertSee('agenda-daybar-hour', false);
        $res->assertSee('agenda-daybar-axis', false);
        $res->assertSee('Math.ceil(startM / 60)', false);
        $res->assertSee('esc(fmtHour(h))', false);
    }

    public function test_daybar_shows_the_selected_time_below_the_marker(): void
    {
        $res = $this->createPage();

        $res->assertSee('agenda-daybar-pick', false);
        $res->assertSee('▲ ', false);
    }

    public function test_service_block_is_draggable_and_edge_resizable(): void
    {
        // El bloque de la cita (barra del operador) es del ancho de su duración; se arrastra
        // para mover y se toma por un borde para cambiar inicio/fin.
        $res = $this->createPage();

        $res->assertSee('agenda-daybar-sel', false);
        $res->assertSee('function selectedDurationMinutes(', false);
        $res->assertSee('function snapToFit(', false);
        $res->assertSee('function attachBlockDrag(', false);
        $res->assertSee('function wireOperatorBlock(', false);
        $res->assertSee("mode === 'start'", false);   // resize por el borde izquierdo
        $res->assertSee("'ew-resize'", false);          // cursor de redimensionar cerca del borde
        $res->assertSee("addEventListener('pointerdown'", false);
        $res->assertSee('commitSelectedMinute(snapToFit(', false);
        // La duración real de la cita viaja al chequeo de disponibilidad.
        $res->assertSee('duration_minutes: selectedDurationMinutes()', false);
    }

    public function test_cage_stay_is_its_own_datetime_window_independent_of_the_operator(): void
    {
        // La estancia en la jaula NO es el bloque del operador: entra con el servicio y sale
        // cuando se indique, incluso otro día. Se maneja con un campo de fecha/hora propio.
        $res = $this->createPage();

        $res->assertSee('name="resource_starts_at"', false);
        $res->assertSee('name="resource_ends_at"', false);
        $res->assertSee('id="resource_starts_at_input"', false);
        $res->assertSee('id="resource_ends_at_input"', false);
        $res->assertSee('Estancia en la jaula', false);
        $res->assertSee('Entra al terminar el servicio', false);   // preset pedido por Tomas
        $res->assertSee('data-stay-preset="service-end"', false);
        $res->assertSee('function syncStay(', false);
        $res->assertSee('stayStartTouched', false);
        $res->assertSee('stayEndTouched', false);
        $res->assertSee("params.set('resource_starts_at'", false);
        // Por defecto la estancia arranca al TERMINAR el servicio, no al iniciarlo.
        $res->assertSee('if (!stayStartTouched) setFp(resourceStartsInput, serviceEndDate());', false);
        // `checkAvailability` está debounceado (no dispara una ráfaga de fetches).
        $res->assertSee('function _checkAvailabilityRun(', false);
        $res->assertSee('setTimeout(_checkAvailabilityRun', false);
        // La estancia también se ajusta con los campos Entra/Sale + botones (además de
        // arrastrar el bloque, ver `wireStayBlock` / SYNC-091) — estos helpers previos NUNCA
        // existieron con estos nombres.
        $res->assertDontSee('function wireStayBar(', false);
        $res->assertDontSee('function wireResourceBlock(', false);
        $res->assertDontSee('agenda-daybar--res', false);
    }

    public function test_cage_stay_bar_renders_under_the_operator_bar_and_is_draggable_unless_multi_day(): void
    {
        // Tomas (SYNC-086): la barra de la estancia va DENTRO del panel de disponibilidad,
        // justo debajo de la del operador y con la misma escala [w0,w1] — NO en el recuadro de
        // jaula. Tomas (SYNC-091): más fácil arrastrar/redimensionar que teclear horas — la
        // barra vuelve a ser interactiva (misma máquina `attachBlockDrag` que el bloque del
        // operador, que solo toca campos locales + `checkAvailability()` de lectura, nunca
        // escribe la reserva — eso solo pasa en `storeForPet`, con su candado anti doble-submit,
        // así que el bug de doble/triple asignación de la primera versión arrastrable no aplica
        // aquí). Excepción: una estancia que cruza medianoche se queda de solo lectura, el
        // rango de arrastre es en minutos-de-un-día.
        $res = $this->createPage();

        $res->assertSee('function stayBarRow(w0, w1)', false);
        // Se concatena en el innerHTML del panel, después de dayTimeline.
        $res->assertSee('stayBarRow(w0, w1) +', false);
        // Misma clase que la del operador pero marcada `agenda-stay-bar` → el cableado del
        // operador la excluye con `:not(.agenda-stay-bar)`.
        $res->assertSee('agenda-stay-bar', false);
        $res->assertSee('.agenda-daybar:not(.agenda-stay-bar)', false);
        // Interactiva salvo cruce de medianoche.
        $res->assertSee('var interactive = !multiDay;', false);
        $res->assertSee('function wireStayBlock(bar, sel, busy)', false);
        $res->assertSee("querySelector('.agenda-daybar-sel:not(.is-readonly)')", false);
        $res->assertSee('agenda-daybar-draghint', false);
        // El cruce de medianoche se queda de solo lectura (pointer-events:none vía CSS).
        $res->assertSee('.agenda-daybar-sel.is-readonly', false);
        $res->assertSee('agenda-daybar-ghost', false);   // sombra del servicio
        // Ya NO vive en el recuadro de jaula.
        $res->assertDontSee('id="stay_bar"', false);
        $res->assertDontSee('function renderStayBar(', false);
        // Candado anti doble-submit del form de crear cita.
        $res->assertSee('id="agenda-create-form"', false);
        $res->assertSee('id="agenda-create-submit"', false);
    }

    public function test_page_ships_the_next_slot_search_ui(): void
    {
        // Cuando la selección no cabe, el panel ofrece "Buscar el próximo hueco" + opciones
        // (desde / días / permitir otro operador calificado) contra GET /agenda/next-slot.
        $res = $this->createPage();

        $res->assertSee('function nextSlotUi(', false);
        $res->assertSee('function wireNextSlot(', false);
        $res->assertSee('Buscar el próximo hueco', false);
        $res->assertSee('Permitir otro operador calificado si este no tiene lugar', false);
        $res->assertSee('/agenda/next-slot', false);
        // El fix del "salta al cierre": reacomoda al primer hueco del día al cambiar de fecha.
        $res->assertSee('dayJustChanged', false);
    }

    public function test_the_initial_suggested_time_can_self_correct_to_the_first_fitting_slot(): void
    {
        // La hora sugerida al abrir la página ("ahora + 1h", `$suggested` en el @php de arriba)
        // no conoce la agenda real de ningún operador todavía — puede caer fuera de su horario
        // o encima de una cita ya agendada. `dayJustChanged` debe arrancar en `true` para que el
        // primer `renderAvailability()` la reacomode igual que un cambio de fecha explícito, en
        // vez de dejarla pegada al horario de cierre / dentro de un choque sin avisar de forma
        // útil. Antes arrancaba en `false` y el primer render nunca se autocorregía.
        $res = $this->createPage();

        $res->assertSee('var dayJustChanged = true;', false);
    }

    public function test_daybar_times_follow_the_system_time_format(): void
    {
        // El backend siempre da "HH:MM" 24h; la barra los pasa por fmtHM/fmtHour, que
        // respetan el ajuste del sistema (body[data-time24h]) igual que el resto del backoffice.
        $res = $this->createPage();

        $res->assertSee("document.body.dataset.time24h === '1'", false);
        $res->assertSee('function fmtHM(', false);
        $res->assertSee('fmtHM(selectedHHMM)', false);
    }

    public function test_a_12h_system_flags_the_page_as_12h(): void
    {
        app(SystemSettings::class)->saveFields('system', ['system_time_format' => '12h']);

        $this->createPage()->assertSee('data-time24h="0"', false);
    }

    public function test_a_24h_system_flags_the_page_as_24h_for_picker_and_daybar(): void
    {
        // El selector (datetime-picker.js: is24h) y la barra (create.blade: USE_24H) leen
        // el mismo body[data-time24h]. Con el sistema en 24h debe salir "1".
        app(SystemSettings::class)->saveFields('system', ['system_time_format' => '24h']);

        $res = $this->createPage();

        $res->assertSee('data-time24h="1"', false);
    }
}
