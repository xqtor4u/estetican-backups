<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Support\Pages\ClinicalPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalController extends Controller
{
    public function index(Request $request): View
    {
        $page = ClinicalPage::index();
        $search = trim((string) $request->query('search', ''));

        $pets = Pet::query()
            ->with('client:id,first_name,apellido_paterno,apellido_materno')
            ->whereNull('death_date')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('apellido_paterno', 'like', "%{$search}%")
                            ->orWhere('apellido_materno', 'like', "%{$search}%");
                    });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('clinical.index', compact('page', 'pets', 'search'));
    }
}
