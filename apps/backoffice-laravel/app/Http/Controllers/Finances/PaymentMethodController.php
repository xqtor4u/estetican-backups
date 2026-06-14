<?php

namespace App\Http\Controllers\Finances;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::with('account')
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->get();

        return view('finances.payment-methods.index', compact('methods'));
    }

    public function create(): View
    {
        $accounts = Account::where('is_active', true)
            ->where('allows_entries', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('finances.payment-methods.create', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        PaymentMethod::create([
            'code'               => strtoupper(trim($validated['code'])),
            'name'               => trim($validated['name']),
            'type'               => $validated['type'],
            'account_id'         => $validated['account_id'] ?: null,
            'requires_reference' => !empty($validated['requires_reference']),
            'is_active'          => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.payment-methods.index')
            ->with('success', 'Método de pago creado.');
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        $accounts = Account::where('is_active', true)
            ->where('allows_entries', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return view('finances.payment-methods.edit', compact('paymentMethod', 'accounts'));
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate($this->rules($paymentMethod));

        $paymentMethod->update([
            'code'               => strtoupper(trim($validated['code'])),
            'name'               => trim($validated['name']),
            'type'               => $validated['type'],
            'account_id'         => $validated['account_id'] ?: null,
            'requires_reference' => !empty($validated['requires_reference']),
            'is_active'          => !empty($validated['is_active']),
        ]);

        return redirect()->route('finances.payment-methods.index')
            ->with('success', 'Método de pago actualizado.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()->route('finances.payment-methods.index')
            ->with('success', 'Método de pago eliminado.');
    }

    private function rules(?PaymentMethod $method = null): array
    {
        return [
            'code'               => ['required', 'string', 'max:30', Rule::unique('payment_methods', 'code')->ignore($method?->id)],
            'name'               => ['required', 'string', 'max:255'],
            'type'               => ['required', Rule::in(['cash', 'card', 'transfer', 'crypto', 'gateway'])],
            'account_id'         => ['nullable', 'exists:accounts,id'],
            'requires_reference' => ['nullable', 'boolean'],
            'is_active'          => ['nullable', 'boolean'],
        ];
    }
}
