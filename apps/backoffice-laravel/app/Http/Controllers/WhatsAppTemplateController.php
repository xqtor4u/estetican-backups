<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use App\Support\Pages\WhatsAppPage;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppTemplateController extends Controller
{
    public function index(): View
    {
        $templates = WhatsAppTemplate::withCount(['messages', 'recurrenceMessages'])->orderBy('name')->get();

        return view('whatsapp.plantillas.index', [
            'templates' => $templates,
            'page' => WhatsAppPage::plantillasIndex(),
        ]);
    }

    public function create(): View
    {
        return view('whatsapp.plantillas.create', [
            'variablesByContext' => $this->variablesByContext(),
            'page' => WhatsAppPage::plantillasCreate(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate($this->rules());

        $template = WhatsAppTemplate::create([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'context' => $validated['context'],
            'is_active' => ! empty($validated['is_active']),
            'created_by_user_id' => $request->user()->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'template' => ['id' => $template->id, 'name' => $template->name],
            ], 201);
        }

        return redirect()->route('whatsapp.plantillas.index')
            ->with('success', 'Plantilla creada correctamente.');
    }

    public function edit(WhatsAppTemplate $template): View
    {
        return view('whatsapp.plantillas.edit', [
            'template' => $template,
            'variablesByContext' => $this->variablesByContext(),
            'page' => WhatsAppPage::plantillasEdit($template),
        ]);
    }

    public function update(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $template->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'context' => $validated['context'],
            'is_active' => ! empty($validated['is_active']),
        ]);

        return redirect()->route('whatsapp.plantillas.index')
            ->with('success', 'Plantilla actualizada.');
    }

    public function destroy(WhatsAppTemplate $template): RedirectResponse
    {
        if ($template->messages()->exists() || $template->recurrenceMessages()->exists()) {
            return redirect()->route('whatsapp.plantillas.index')
                ->with('error', 'No se puede eliminar una plantilla ya usada en envíos. Desactívala en su lugar.');
        }

        $template->delete();

        return redirect()->route('whatsapp.plantillas.index')
            ->with('success', 'Plantilla eliminada.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'context' => ['required', 'string', 'in:cita,recurrencia,cliente'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function variablesByContext(): array
    {
        return [
            'cita' => TemplateResolver::availableVariables('cita'),
            'recurrencia' => TemplateResolver::availableVariables('recurrencia'),
            'cliente' => TemplateResolver::availableVariables('cliente'),
        ];
    }
}
