<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CourierController extends Controller
{
    private function adminOnly(): void
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }
    }

    public function index()
    {
        $this->adminOnly();

        return view('admin.couriers.index', [
            'title' => 'Courier Manage',
            'couriers' => Courier::query()
                ->latest()
                ->paginate(20),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Courier Manage', 'url' => route('admin.couriers.index')],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('couriers', 'code')->whereNull('deleted_at'),
            ],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Courier code only supports letters, numbers, dash and underscore.',
            'code.unique' => 'This courier code already exists.',
        ]);

        Courier::create([
            'name' => $validated['name'],
            'code' => $this->makeCode($validated['code'] ?? null, $validated['name']),
            'merchant_id' => $validated['merchant_id'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'status' => $request->boolean('status', true),
        ]);

        return back()->with('success', 'Courier created successfully.');
    }

    public function update(Request $request, Courier $courier)
    {
        $this->adminOnly();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('couriers', 'code')
                    ->ignore($courier->id)
                    ->whereNull('deleted_at'),
            ],
            'merchant_id' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'Courier code only supports letters, numbers, dash and underscore.',
            'code.unique' => 'This courier code already exists.',
        ]);

        $courier->update([
            'name' => $validated['name'],
            'code' => $this->makeCode($validated['code'] ?? null, $validated['name']),
            'merchant_id' => $validated['merchant_id'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'status' => $request->boolean('status'),
        ]);

        return back()->with('success', 'Courier updated successfully.');
    }

    public function destroy(Courier $courier)
    {
        $this->adminOnly();

        $courier->delete();

        return back()->with('success', 'Courier deleted successfully.');
    }

    private function makeCode(?string $code, string $name): string
    {
        $code = $code ?: $name;

        $code = Str::slug($code, '_');

        return strtolower($code ?: 'courier_' . time());
    }
}