<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('admin.suppliers.index', ['suppliers' => Supplier::query()->withCount('purchases')->orderBy('name')->paginate(30)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'সরবরাহকারী যোগ হয়েছে।');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return back()->with('success', 'সরবরাহকারীর তথ্য আপডেট হয়েছে।');
    }

    public function toggle(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return back()->with('success', 'সরবরাহকারীর অবস্থা আপডেট হয়েছে।');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ]);
        $data['is_active'] = $request->boolean('is_active', $supplier?->is_active ?? true);

        return $data;
    }
}
