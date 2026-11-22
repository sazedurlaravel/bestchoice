<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::query()->where('role', 'staff')->latest()->paginate(30),
            'permissions' => User::permissionLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'permissions' => $data['permissions'] ?? [],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.staff')->with('success', 'স্টাফ অ্যাকাউন্ট তৈরি হয়েছে।');
    }

    public function update(Request $request, User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $data = $this->validated($request, $staff);
        $staff->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'permissions' => $data['permissions'] ?? [],
            'is_active' => $request->boolean('is_active'),
        ]);
        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
        }
        $staff->save();

        return redirect()->route('admin.staff')->with('success', 'স্টাফ অ্যাকাউন্ট আপডেট হয়েছে।');
    }

    public function destroy(User $staff): RedirectResponse
    {
        abort_unless($staff->role === 'staff', 404);
        $staff->update(['is_active' => false]);

        return redirect()->route('admin.staff')->with('success', 'স্টাফ অ্যাকাউন্ট নিষ্ক্রিয় করা হয়েছে।');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $staff = null): array
    {
        $passwordRules = $staff ? ['nullable', 'confirmed', 'string', 'min:8'] : ['required', 'confirmed', 'string', 'min:8'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.($staff?->id ?? 'NULL')],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile,'.($staff?->id ?? 'NULL')],
            'password' => $passwordRules,
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'in:'.implode(',', User::permissionKeys())],
            'is_active' => ['boolean'],
        ]);
    }
}
