<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppTemplate;
use App\Support\Pages\WhatsAppPage;
use App\Support\WhatsApp\TemplateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppTemplateController extends Controller
{
    public function index(): View
    {
        $templates = WhatsAppTemplate::withCount('messages')->orderBy('name')->get();

        return view('whatsapp.plantillas.index', [
            'templates' => $templates,
            'page' => WhatsAppPage::plantillasIndex(),
        ]);
    }

    public function create(): View
    {
        return view('whatsapp.plantillas.create', [
            'variables' => TemplateResolver::availableVariables(),
            'page' => WhatsAppPage::plantillasCreate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        WhatsAppTemplate::create([
            'name' => $validated['name'],
            'body' => $validated['body'],
            'is_active' => ! empty($validated['is_active']),
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()->route('whatsapp.plantillas.index')
            ->with('success', 'Plantilla creada correctamente.');
    }

    public function edit(WhatsAppTemplate $template): View
    {
        return view('whatsapp.plantillas.edit', [
            'template' => $template,
            'variables' => TemplateResolver::availableVariables(),
            'page' => WhatsAppPage::plantillasEdit($template),
        ]);
    }

    public function update(Request $request, WhatsAppTemplate $template): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $template->update([
            'name' => $validated['name'],
            'body' => $validated['body'],
            'is_active' => ! empty($validated['is_active']),
        ]);

        return redirect()->route('whatsapp.plantillas.index')
            ->with('success', 'Plantilla actualizada.');
    }

    public function destroy(WhatsAppTemplate $template): RedirectResponse
    {
        if ($template->messages()->exists()) {
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
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
