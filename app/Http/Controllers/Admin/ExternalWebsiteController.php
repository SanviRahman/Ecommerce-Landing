<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExternalWebsiteRequest;
use App\Models\ExternalOrderSync;
use App\Models\ExternalWebsite;
use App\Services\ExternalOrderSyncService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ExternalWebsiteController extends Controller
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

        return view('admin.external-websites.index', [
            'title' => 'Website Order Sync',
            'localWebsiteName' => config('app.name'),
            'websites' => ExternalWebsite::query()
                ->withCount([
                    'orders',
                    'outboundSyncs as sent_orders_count' => fn ($query) =>
                        $query->where('status', ExternalOrderSync::STATUS_SENT),
                    'outboundSyncs as failed_orders_count' => fn ($query) =>
                        $query->where('status', ExternalOrderSync::STATUS_FAILED),
                    'outboundSyncs as pending_orders_count' => fn ($query) =>
                        $query->whereIn('status', [
                            ExternalOrderSync::STATUS_PENDING,
                            ExternalOrderSync::STATUS_SENDING,
                        ]),
                ])
                ->latest()
                ->paginate(20),
            'breadcrumb' => [
                ['text' => 'Dashboard', 'url' => route('admin.dashboard')],
                ['text' => 'Website Order Sync', 'url' => route('admin.external-websites.index')],
            ],
        ]);
    }

    public function store(StoreExternalWebsiteRequest $request)
    {
        $this->adminOnly();

        $validated = $request->validated();
        $receiverToken = $this->resolveReceiverToken(
            $validated['token_action'],
            $validated['api_token'] ?? null
        );

        $website = ExternalWebsite::query()->create([
            'name' => trim($validated['name']),
            'slug' => $this->uniqueSlug($validated['name'], $validated['domain']),
            'domain' => $validated['domain'],
            'api_token' => $receiverToken,
            'token_updated_at' => now(),
            'status' => (bool) $validated['status'],
            'receive_orders' => (bool) $validated['receive_orders'],
            'send_orders' => (bool) $validated['send_orders'],
            'auto_send_orders' => (bool) $validated['auto_send_orders'],
            'remote_order_endpoint' => $validated['remote_order_endpoint'] ?? null,
            'remote_health_endpoint' => $validated['remote_health_endpoint'] ?? null,
            'remote_api_token' => $validated['remote_api_token'] ?: null,
            'request_timeout' => (int) $validated['request_timeout'],
            'notes' => $validated['notes'] ?? null,
            'inbound_approval_status' => ExternalWebsite::INBOUND_APPROVAL_AWAITING_REQUEST,
        ]);

        return redirect()
            ->route('admin.external-websites.index')
            ->with('success', 'Bidirectional website integration created successfully.')
            ->with('new_api_token', $receiverToken)
            ->with('new_api_token_website_id', $website->id)
            ->with('new_api_endpoint', $website->api_endpoint)
            ->with('new_health_endpoint', $website->health_endpoint);
    }

    public function update(
        StoreExternalWebsiteRequest $request,
        ExternalWebsite $externalWebsite
    ) {
        $this->adminOnly();

        $validated = $request->validated();
        $newReceiverToken = null;

        DB::transaction(function () use (
            $validated,
            $externalWebsite,
            &$newReceiverToken
        ): void {
            $updates = [
                'name' => trim($validated['name']),
                'domain' => $validated['domain'],
                'status' => (bool) $validated['status'],
                'receive_orders' => (bool) $validated['receive_orders'],
                'send_orders' => (bool) $validated['send_orders'],
                'auto_send_orders' => (bool) $validated['auto_send_orders'],
                'remote_order_endpoint' => $validated['remote_order_endpoint'] ?? null,
                'remote_health_endpoint' => $validated['remote_health_endpoint'] ?? null,
                'request_timeout' => (int) $validated['request_timeout'],
                'notes' => $validated['notes'] ?? null,
                'last_connection_tested_at' => null,
                'last_connection_status' => null,
                'last_connection_message' => null,
            ];

            if ($validated['token_action'] !== 'keep') {
                $newReceiverToken = $this->resolveReceiverToken(
                    $validated['token_action'],
                    $validated['api_token'] ?? null
                );

                $updates['api_token'] = $newReceiverToken;
                $updates['token_updated_at'] = now();
                $updates['last_authenticated_at'] = null;
                $updates['last_auth_failed_at'] = null;
                $updates['inbound_approval_status'] = ExternalWebsite::INBOUND_APPROVAL_AWAITING_REQUEST;
                $updates['inbound_request_received_at'] = null;
                $updates['inbound_request_ip'] = null;
                $updates['inbound_request_meta'] = null;
                $updates['inbound_approved_at'] = null;
                $updates['inbound_rejected_at'] = null;
            }

            if (trim((string) ($validated['remote_api_token'] ?? '')) !== '') {
                $updates['remote_api_token'] = trim($validated['remote_api_token']);
            }

            $externalWebsite->update($updates);
        });

        $response = redirect()
            ->route('admin.external-websites.index')
            ->with('success', 'Bidirectional website integration updated successfully.');

        if ($newReceiverToken !== null) {
            $response
                ->with('new_api_token', $newReceiverToken)
                ->with('new_api_token_website_id', $externalWebsite->id)
                ->with('new_api_endpoint', $externalWebsite->fresh()->api_endpoint)
                ->with('new_health_endpoint', $externalWebsite->fresh()->health_endpoint);
        }

        return $response;
    }

    public function regenerateToken(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        $token = Str::random(64);

        $externalWebsite->update([
            'api_token' => $token,
            'token_updated_at' => now(),
            'last_authenticated_at' => null,
            'last_auth_failed_at' => null,
            'inbound_approval_status' => ExternalWebsite::INBOUND_APPROVAL_AWAITING_REQUEST,
            'inbound_request_received_at' => null,
            'inbound_request_ip' => null,
            'inbound_request_meta' => null,
            'inbound_approved_at' => null,
            'inbound_rejected_at' => null,
        ]);

        return redirect()
            ->route('admin.external-websites.index')
            ->with('success', 'A new receiver token was generated and saved in the database.')
            ->with('new_api_token', $token)
            ->with('new_api_token_website_id', $externalWebsite->id)
            ->with('new_api_endpoint', $externalWebsite->api_endpoint)
            ->with('new_health_endpoint', $externalWebsite->health_endpoint);
    }

    public function testConnection(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        if (! $externalWebsite->canSendOrders()) {
            return back()->with(
                'error',
                'Enable Send Orders and save the remote endpoint and remote token first.'
            );
        }

        $healthEndpoint = $externalWebsite->resolved_remote_health_endpoint;

        if (! $healthEndpoint) {
            return back()->with('error', 'Remote health endpoint could not be resolved.');
        }

        try {
            $response = Http::acceptJson()
                ->withToken((string) $externalWebsite->remote_api_token)
                ->timeout(max(3, (int) $externalWebsite->request_timeout))
                ->get($healthEndpoint);

            $responseData = $response->json();

            if (
                $response->status() === 403
                && is_array($responseData)
                && ($responseData['code'] ?? null) === 'approval_required'
            ) {
                return $this->sendConnectionRequest($externalWebsite);
            }

            $connected = $response->successful()
                && (! is_array($responseData) || ($responseData['status'] ?? true) === true);
            $message = is_array($responseData)
                ? (string) ($responseData['message'] ?? '')
                : '';

            if ($message === '') {
                $message = $connected
                    ? 'Connection successful.'
                    : 'Connection test failed with HTTP ' . $response->status() . '.';
            }

            $externalWebsite->forceFill([
                'last_connection_tested_at' => now(),
                'last_connection_status' => $connected ? 'connected' : 'failed',
                'last_connection_message' => $this->limitText($message),
            ])->saveQuietly();

            $redirect = back()->with(
                $connected ? 'success' : 'error',
                $connected
                    ? "Connected to {$externalWebsite->name} successfully."
                    : "Could not connect to {$externalWebsite->name}: {$message}"
            );

            if ($connected && $externalWebsite->auto_send_orders) {
                $redirect->with('auto_sync_existing_website_id', $externalWebsite->id);
            }

            return $redirect;
        } catch (ConnectionException $exception) {
            return $this->connectionFailed($externalWebsite, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->connectionFailed($externalWebsite, $exception->getMessage());
        }
    }

    public function approveInboundConnection(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        if (! $externalWebsite->status || ! $externalWebsite->receive_orders) {
            return back()->with('error', 'Enable this integration and Receive Orders before approving the connection.');
        }

        $externalWebsite->forceFill([
            'inbound_approval_status' => ExternalWebsite::INBOUND_APPROVAL_APPROVED,
            'inbound_approved_at' => now(),
            'inbound_rejected_at' => null,
        ])->saveQuietly();

        $successMessage = "Connection request from {$externalWebsite->name} accepted successfully. The receive connection is now connected; ask the sender to run Test Connection once to mark its send connection as connected.";

        return back()
            ->with('success', $successMessage)
            ->with('connection_swal', [
                'icon' => 'success',
                'title' => 'Request Accepted Successfully',
                'text' => $successMessage,
            ]);
    }

    public function rejectInboundConnection(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        $externalWebsite->forceFill([
            'inbound_approval_status' => ExternalWebsite::INBOUND_APPROVAL_REJECTED,
            'inbound_rejected_at' => now(),
            'inbound_approved_at' => null,
        ])->saveQuietly();

        $successMessage = "Connection request from {$externalWebsite->name} rejected.";

        return back()
            ->with('success', $successMessage)
            ->with('connection_swal', [
                'icon' => 'success',
                'title' => 'Request Rejected',
                'text' => $successMessage,
            ]);
    }

    public function sendConnectionRequest(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        if (! $externalWebsite->canSendOrders()) {
            return back()->with(
                'error',
                'Enable Send Orders and save the remote endpoint and remote token before sending a connection request.'
            );
        }

        $requestEndpoint = rtrim((string) $externalWebsite->remote_order_endpoint, '/')
            . '/connection-request';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) $externalWebsite->remote_api_token)
                ->timeout(max(3, (int) $externalWebsite->request_timeout))
                ->post($requestEndpoint, [
                    'source_website_name' => (string) config('app.name'),
                    'source_website_domain' => rtrim((string) config('app.url'), '/'),
                ]);

            $data = $response->json();
            $message = is_array($data)
                ? (string) ($data['message'] ?? '')
                : '';

            if ($response->successful()) {
                $alreadyApproved = is_array($data) && ($data['approved'] ?? false) === true;

                $externalWebsite->forceFill([
                    'last_connection_tested_at' => now(),
                    'last_connection_status' => $alreadyApproved ? 'connected' : 'pending_approval',
                    'last_connection_message' => $this->limitText(
                        $message ?: ($alreadyApproved
                            ? 'Connection is already approved.'
                            : 'Connection request sent. Waiting for receiver admin approval.')
                    ),
                ])->saveQuietly();

                $successMessage = $alreadyApproved
                    ? "Connected to {$externalWebsite->name} successfully."
                    : "Connection request sent to {$externalWebsite->name} successfully. Approve it from the receiver admin panel, then run Test Connection.";

                $redirect = back()
                    ->with('success', $successMessage)
                    ->with('connection_swal', [
                        'icon' => 'success',
                        'title' => $alreadyApproved ? 'Connection Already Approved' : 'Request Sent Successfully',
                        'text' => $successMessage,
                    ]);

                if ($alreadyApproved && $externalWebsite->auto_send_orders) {
                    $redirect->with('auto_sync_existing_website_id', $externalWebsite->id);
                }

                return $redirect;
            }

            return $this->connectionFailed(
                $externalWebsite,
                $message ?: 'Connection request failed with HTTP ' . $response->status() . '.'
            );
        } catch (ConnectionException $exception) {
            return $this->connectionFailed($externalWebsite, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->connectionFailed($externalWebsite, $exception->getMessage());
        }
    }

    public function syncExistingOrders(
        Request $request,
        ExternalWebsite $externalWebsite,
        ExternalOrderSyncService $syncService
    ) {
        $this->adminOnly();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! $externalWebsite->canSendOrders()) {
            $message = 'Outgoing sync is disabled or the remote endpoint/token is missing.';

            return $request->expectsJson()
                ? response()->json(['status' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        if ($externalWebsite->last_connection_status !== 'connected') {
            $message = 'Test the connection successfully before syncing existing orders.';

            return $request->expectsJson()
                ? response()->json(['status' => false, 'message' => $message], 409)
                : back()->with('error', $message);
        }

        $result = $syncService->syncExistingOrders(
            $externalWebsite,
            (int) ($validated['limit'] ?? 20)
        );

        $message = "Existing order sync batch finished. Sent: {$result['sent']}, Failed: {$result['failed']}, Remaining: {$result['remaining']}.";

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'message' => $message,
                'data' => $result,
            ]);
        }

        return back()->with('success', $message);
    }

    public function retryFailedOrders(
        Request $request,
        ExternalWebsite $externalWebsite,
        ExternalOrderSyncService $syncService
    ) {
        $this->adminOnly();

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $result = $syncService->retryFailedOrders(
            $externalWebsite,
            (int) ($validated['limit'] ?? 100)
        );

        return back()->with(
            'success',
            "Failed order retry finished. Sent: {$result['sent']}, Still failed: {$result['failed']}."
        );
    }

    public function destroy(ExternalWebsite $externalWebsite)
    {
        $this->adminOnly();

        $externalWebsite->delete();

        return back()->with(
            'success',
            'Website integration deleted. Existing received orders remain unchanged.'
        );
    }

    private function connectionFailed(
        ExternalWebsite $externalWebsite,
        string $message
    ) {
        $message = $this->limitText($message);

        $externalWebsite->forceFill([
            'last_connection_tested_at' => now(),
            'last_connection_status' => 'failed',
            'last_connection_message' => $message,
        ])->saveQuietly();

        return back()->with(
            'error',
            "Could not connect to {$externalWebsite->name}: {$message}"
        );
    }

    private function resolveReceiverToken(string $tokenAction, ?string $manualToken): string
    {
        if ($tokenAction === 'manual') {
            return trim((string) $manualToken);
        }

        return Str::random(64);
    }

    private function uniqueSlug(string $name, string $domain): string
    {
        $host = (string) (parse_url($domain, PHP_URL_HOST) ?: $name);
        $baseSlug = Str::slug($name) ?: Str::slug($host) ?: 'website';
        $slug = $baseSlug;
        $counter = 2;

        while (ExternalWebsite::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function limitText(?string $value, int $limit = 2000): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'Unknown connection error.';
        }

        return mb_substr($value, 0, $limit);
    }
}
