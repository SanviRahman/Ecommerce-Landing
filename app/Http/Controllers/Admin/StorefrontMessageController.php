<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StorefrontContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

final class StorefrontMessageController extends Controller
{
    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }

    public function index(Request $request): View
    {
        $this->adminOnly();
        $data = $request->validate(['status' => ['nullable', Rule::in(['new','read','replied','archived'])]]);
        $messages = StorefrontContactMessage::query()
            ->when($data['status'] ?? null, static fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();
        return view('admin.storefront-messages.index', [
            'messages' => $messages,
            'newCount' => StorefrontContactMessage::query()->where('status','new')->count(),
        ]);
    }

    public function show(StorefrontContactMessage $message): View
    {
        $this->adminOnly();
        return view('admin.storefront-messages.show', compact('message'));
    }

    public function update(Request $request, StorefrontContactMessage $message): RedirectResponse
    {
        $this->adminOnly();
        $data = $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'replied', 'archived'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($message, $data): void {
            $message->update([
                'status' => $data['status'],
                'admin_note' => $data['admin_note'] ?? null,
                'read_at' => $data['status'] === 'new' ? null : ($message->read_at ?? now()),
            ]);
        });
        return redirect()->route('admin.storefront-messages.show', $message)
            ->with('success', 'Message updated. No outgoing email was sent.');
    }

    public function destroy(StorefrontContactMessage $message): RedirectResponse
    {
        $this->adminOnly();
        $message->delete(); // soft delete; never destroy customer correspondence automatically
        return redirect()->route('admin.storefront-messages.index')->with('success', 'Message archived to trash.');
    }
}
