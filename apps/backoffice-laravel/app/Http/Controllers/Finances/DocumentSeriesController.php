<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DocumentSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentSeriesController extends Controller
{
    public function index(): View
    {
        $series = DocumentSeries::with('branch')
            ->orderByDesc('is_active')
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();

        return view('finances.document-series.index', compact('series'));
    }

    public function create(): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('finances.document-series.create', compact('branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        DocumentSeries::create([
            'document_type' => $validated['document_type'],
            'name'          => trim($validated['name']),
            'prefix'        => trim($validated['prefix']),
            'suffix'        => trim($validated['suffix'] ?? ''),
            'next_number'   => (int) ($validated['next_number'] ?? 1),
            'padding'       => (int) ($validated['padding'] ?? 4),
            'branch_id'     => $validated['branch_id'] ?: null,
            'is_active'     => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.document-series.index')
            ->with('success', 'Serie creada.');
    }

    public function edit(DocumentSeries $documentSeries): View
    {
        $branches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('finances.document-series.edit', compact('documentSeries', 'branches'));
    }

    public function update(Request $request, DocumentSeries $documentSeries): RedirectResponse
    {
        $validated = $request->validate($this->rules($documentSeries));

        $documentSeries->update([
            'document_type' => $validated['document_type'],
            'name'          => trim($validated['name']),
            'prefix'        => trim($validated['prefix']),
            'suffix'        => trim($validated['suffix'] ?? ''),
            'padding'       => (int) ($validated['padding'] ?? 4),
            'branch_id'     => $validated['branch_id'] ?: null,
            'is_active'     => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.document-series.index')
            ->with('success', 'Serie actualizada.');
    }

    public function destroy(DocumentSeries $documentSeries): RedirectResponse
    {
        if ($documentSeries->documents()->exists()) {
            return redirect()->route('finances.document-series.index')
                ->with('error', 'No se puede eliminar una serie que ya tiene documentos emitidos.');
        }

        $documentSeries->delete();

        return redirect()->route('finances.document-series.index')
            ->with('success', 'Serie eliminada.');
    }

    private function rules(?DocumentSeries $series = null): array
    {
        return [
            'document_type' => ['required', Rule::in(['recibo', 'factura', 'sin_documento'])],
            'name'          => ['required', 'string', 'max:255'],
            'prefix'        => ['required', 'string', 'max:20'],
            'suffix'        => ['nullable', 'string', 'max:20'],
            'next_number'   => ['nullable', 'integer', 'min:1'],
            'padding'       => ['required', 'integer', 'min:1', 'max:10'],
            'branch_id'     => ['nullable', 'exists:branches,id'],
            'is_active'     => ['nullable', 'boolean'],
        ];
    }
}
