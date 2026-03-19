<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clients = Client::with('addresses', 'phones')->paginate(10);
        return view('clients.index', compact('clients'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('clients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients',
            'addresses' => 'array',
            'addresses.*.type' => 'required|string',
            'addresses.*.street' => 'required|string',
            'addresses.*.colonia' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.country' => 'required|string',
            'phones' => 'array',
            'phones.*.number' => 'required|string',
            'phones.*.type' => 'required|string',
        ]);

        $client = Client::create($request->only(['first_name', 'last_name', 'email', 'address', 'city', 'state', 'zip_code', 'notes']));

        if ($request->addresses) {
            foreach ($request->addresses as $addressData) {
                $client->addresses()->create($addressData);
            }
        }

        if ($request->phones) {
            foreach ($request->phones as $phoneData) {
                $client->phones()->create($phoneData);
            }
        }

        return redirect()->route('clients.index')->with('success', 'Cliente creado.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        $client->load('addresses', 'phones');
        return view('clients.show', compact('client'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email,' . $client->id,
            'addresses' => 'array',
            'addresses.*.type' => 'required|string',
            'addresses.*.street' => 'required|string',
            'addresses.*.colonia' => 'nullable|string',
            'addresses.*.city' => 'required|string',
            'addresses.*.country' => 'required|string',
            'phones' => 'array',
            'phones.*.number' => 'required|string',
            'phones.*.type' => 'required|string',
        ]);

        $client->update($request->only(['first_name', 'last_name', 'email', 'address', 'city', 'state', 'zip_code', 'notes']));

        if ($request->addresses) {
            foreach ($request->addresses as $addressData) {
                $client->addresses()->create($addressData);
            }
        }

        if ($request->phones) {
            foreach ($request->phones as $phoneData) {
                $client->phones()->create($phoneData);
            }
        }

        return redirect()->route('clients.index')->with('success', 'Cliente actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Cliente eliminado.');
    }
}