<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        return view('panel.profile.index', compact('user', 'customer'));
    }

    public function edit()
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        return view('panel.profile.edit', compact('user', 'customer'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $customer = $user->getOrCreateCustomer();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'national_code' => 'nullable|string|size:10',
            'birth_date' => ['nullable', 'string', 'regex:/^[0-9]{4}\/(0[1-9]|1[0-2])\/(0[1-9]|[12][0-9]|3[01])$/'],
            'business_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'postal_code' => 'nullable|string|max:10',
        ]);

        // Convert Jalali birth_date to Gregorian
        $birthDate = null;
        if (!empty($validated['birth_date'])) {
            $birthDate = Jalalian::fromFormat('Y/m/d', $validated['birth_date'])->toCarbon()->format('Y-m-d');
        }

        // Update user
        $user->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'national_code' => $validated['national_code'],
            'birth_date' => $birthDate,
            'business_name' => $validated['business_name'],
            'address' => $validated['address'],
        ]);

        // Update customer
        $customer->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'national_code' => $validated['national_code'],
            'birth_date' => $birthDate,
            'business_name' => $validated['business_name'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
        ]);

        return redirect()->route('panel.profile')->with('success', 'پروفایل با موفقیت بروزرسانی شد.');
    }
}
