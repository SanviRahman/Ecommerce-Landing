<?php

namespace App\Http\Middleware;

use App\Models\ExternalWebsite;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalWebsiteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $externalWebsite = $request->route('externalWebsite');

        if (! $externalWebsite instanceof ExternalWebsite) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Website integration not found.',
            ], 404);
        }

        if (! $externalWebsite->status) {
            return new JsonResponse([
                'status' => false,
                'message' => 'This website integration is inactive.',
            ], 403);
        }

        if (! $externalWebsite->receive_orders) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Receiving orders is disabled for this website integration.',
            ], 403);
        }

        $providedToken = $request->bearerToken()
            ?: $request->header('X-API-Token')
            ?: $request->header('X-API-Key');

        if (! $externalWebsite->tokenMatches($providedToken)) {
            $externalWebsite->forceFill([
                'last_auth_failed_at' => now(),
            ])->saveQuietly();

            return new JsonResponse([
                'status' => false,
                'message' => 'Invalid API token.',
            ], 401);
        }

        $isConnectionRequest = $request->routeIs('api.external-orders.connection-request');

        if (! $isConnectionRequest && ! $externalWebsite->isInboundApproved()) {
            return new JsonResponse([
                'status' => false,
                'code' => 'approval_required',
                'message' => 'Connection approval is required on the receiver website before orders can be received.',
            ], 403);
        }

        $externalWebsite->forceFill([
            'last_authenticated_at' => now(),
        ])->saveQuietly();

        return $next($request);
    }
}
