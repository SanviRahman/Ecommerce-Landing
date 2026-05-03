<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show logged-in admin profile page.
     */
    public function profile()
    {
        $admin = Auth::user();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Profile', 'url' => route('admin.profile')],
        ];

        return view('admin.admins.profile', [
            'admin'      => $admin,
            'title'      => 'Profile',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Update logged-in admin profile.
     */
    public function updateProfile(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin->id),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required'  => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email'    => 'Please enter a valid email address.',
            'email.unique'   => 'This email address is already taken.',
            'photo.image'    => 'Profile photo must be an image.',
            'photo.mimes'    => 'Profile photo must be jpg, jpeg, png or webp.',
            'photo.max'      => 'Profile photo must not be greater than 2MB.',
        ]);

        $admin->name  = $request->name;
        $admin->email = $request->email;
        $admin->save();

        if ($request->hasFile('photo')) {
            $admin->clearMediaCollection('avatars');
            $admin->addMediaFromRequest('photo')->toMediaCollection('avatars');
        }

        $admin->refresh();

        if ($request->ajax()) {
            return response()->json([
                'status'    => true,
                'message'   => 'Profile updated successfully.',
                'image_url' => $admin->image_url,
                'name'      => $admin->name,
                'email'     => $admin->email,
            ]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Show password change page.
     */
    public function password()
    {
        $admin = Auth::user();

        $breadcrumb = [
            ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
            ['text' => 'Change Password', 'url' => route('admin.change-password')],
        ];

        return view('admin.admins.password', [
            'admin'      => $admin,
            'title'      => 'Change Password',
            'breadcrumb' => $breadcrumb,
        ]);
    }

    /**
     * Update logged-in admin password.
     */
    public function updatePassword(Request $request)
    {
        $admin = Auth::user();

        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'confirmed', 'min:6'],
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required'         => 'New password is required.',
            'password.confirmed'        => 'New password confirmation does not match.',
            'password.min'              => 'New password must be at least 6 characters.',
        ]);

        if (! Hash::check($request->current_password, $admin->password)) {
            return response()->json([
                'status' => false,
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $admin->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        if ($request->ajax()) {
            return response()->json([
                'status'  => true,
                'message' => 'Password updated successfully.',
            ]);
        }

        return back()->with('success', 'Password updated successfully.');
    }
}