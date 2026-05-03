<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display admins/employees list.
     */
    public function index(Request $request)
    {
        $query = User::query()->latest();

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $admins = $query->get();

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'html' => view('admin.admins.partials.table', compact('admins'))->render(),
            ]);
        }

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Admins & Employees', 'url' => route('admin.users.index')],
        ];

        return view('admin.admins.index', [
            'admins' => $admins,
            'title' => 'Admins & Employees',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Show create form for AJAX modal.
     */
    public function create(Request $request)
    {
        if (! $request->ajax()) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'Please use the Add New button to create admin or employee.');
        }

        $admin = null;

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Admins & Employees', 'url' => route('admin.users.index')],
            ['text' => 'Create User', 'url' => route('admin.users.create')],
        ];

        return response()->json([
            'status' => true,
            'html' => view('admin.admins.partials.form', [
                'admin' => $admin,
                'action' => route('admin.users.store'),
                'isEdit' => false,
                'breadcrumb' => $breadcrumb,
            ])->render(),
        ]);
    }

    /**
     * Store new admin/employee.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_EMPLOYEE])],
            'password' => ['required', 'confirmed', 'min:6'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already taken.',
            'role.required' => 'User role is required.',
            'role.in' => 'Selected user role is invalid.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 6 characters.',
            'is_active.required' => 'Status is required.',
            'photo.image' => 'Profile photo must be an image.',
            'photo.mimes' => 'Profile photo must be jpg, jpeg, png or webp.',
            'photo.max' => 'Profile photo must not be greater than 2MB.',
        ]);

        $admin = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'is_active' => $request->boolean('is_active'),
            'email_verified_at' => now(),
        ]);

        if ($request->hasFile('photo')) {
            $admin->addMediaFromRequest('photo')->toMediaCollection('avatars');
        }

        return response()->json([
            'status' => true,
            'message' => $admin->role === User::ROLE_EMPLOYEE
                ? 'Employee created successfully.'
                : 'Admin created successfully.',
        ]);
    }

    /**
     * Show admin/employee details.
     */
    public function show(Request $request, User $user)
    {
        if (! $request->ajax()) {
            return redirect()->route('admin.users.index');
        }

        $admin = $user;

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Admins & Employees', 'url' => route('admin.users.index')],
            ['text' => 'View User', 'url' => route('admin.users.show', $admin->id)],
        ];

        return response()->json([
            'status' => true,
            'html' => view('admin.admins.partials.show', [
                'admin' => $admin,
                'breadcrumb' => $breadcrumb,
            ])->render(),
        ]);
    }

    /**
     * Show edit form for AJAX modal.
     */
    public function edit(Request $request, User $user)
    {
        if (! $request->ajax()) {
            return redirect()->route('admin.users.index');
        }

        $admin = $user;

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Admins & Employees', 'url' => route('admin.users.index')],
            ['text' => 'Edit User', 'url' => route('admin.users.edit', $admin->id)],
        ];

        return response()->json([
            'status' => true,
            'html' => view('admin.admins.partials.form', [
                'admin' => $admin,
                'action' => route('admin.users.update', $admin->id),
                'isEdit' => true,
                'breadcrumb' => $breadcrumb,
            ])->render(),
        ]);
    }

    /**
     * Update admin/employee.
     */
    public function update(Request $request, User $user)
    {
        $admin = $user;

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_EMPLOYEE])],
            'password' => ['nullable', 'confirmed', 'min:6'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already taken.',
            'role.required' => 'User role is required.',
            'role.in' => 'Selected user role is invalid.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 6 characters.',
            'is_active.required' => 'Status is required.',
            'photo.image' => 'Profile photo must be an image.',
            'photo.mimes' => 'Profile photo must be jpg, jpeg, png or webp.',
            'photo.max' => 'Profile photo must not be greater than 2MB.',
        ]);

        if (auth()->id() === $admin->id && $request->role !== User::ROLE_ADMIN) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot change your own role from admin to employee.',
            ], 422);
        }

        $admin->name = $request->name;
        $admin->email = $request->email;
        $admin->role = $request->role;
        $admin->is_active = $request->boolean('is_active');

        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }

        $admin->save();

        if ($request->hasFile('photo')) {
            $admin->clearMediaCollection('avatars');
            $admin->addMediaFromRequest('photo')->toMediaCollection('avatars');
        }

        return response()->json([
            'status' => true,
            'message' => $admin->role === User::ROLE_EMPLOYEE
                ? 'Employee updated successfully.'
                : 'Admin updated successfully.',
        ]);
    }

    /**
     * Active/inactive status update.
     */
    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        if (auth()->id() === $user->id && ! $request->boolean('is_active')) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $user->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User status updated successfully.',
        ]);
    }

    /**
     * Delete admin/employee.
     */
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully.',
        ]);
    }
}