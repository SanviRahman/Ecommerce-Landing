<?php

namespace App\Http\Requests\Admin;

use App\Models\ExternalWebsite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExternalWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => $this->normalizeUrl($this->input('domain')),
            'remote_order_endpoint' => $this->normalizeUrl(
                $this->input('remote_order_endpoint')
            ),
            'remote_health_endpoint' => $this->normalizeUrl(
                $this->input('remote_health_endpoint')
            ),
            'status' => $this->boolean('status'),
            'receive_orders' => $this->boolean('receive_orders'),
            'send_orders' => $this->boolean('send_orders'),
            'auto_send_orders' => $this->boolean('auto_send_orders'),
            'token_action' => $this->filled('token_action')
                ? trim((string) $this->input('token_action'))
                : null,
            'api_token' => $this->filled('api_token')
                ? trim((string) $this->input('api_token'))
                : null,
            'remote_api_token' => $this->filled('remote_api_token')
                ? trim((string) $this->input('remote_api_token'))
                : null,
        ]);
    }

    public function rules(): array
    {
        $externalWebsite = $this->route('externalWebsite')
            ?: $this->route('external_website');
        $isUpdate = $externalWebsite instanceof ExternalWebsite;
        $externalWebsiteId = $isUpdate ? $externalWebsite->id : null;

        $remoteTokenAlreadySaved = false;

        if ($isUpdate) {
            try {
                $remoteTokenAlreadySaved = trim((string) $externalWebsite->remote_api_token) !== '';
            } catch (\Throwable) {
                $remoteTokenAlreadySaved = false;
            }
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'domain' => [
                'required',
                'url:http,https',
                'max:500',
                Rule::unique('external_websites', 'domain')
                    ->whereNull('deleted_at')
                    ->ignore($externalWebsiteId),
            ],
            'status' => ['required', 'boolean'],
            'receive_orders' => ['required', 'boolean'],
            'send_orders' => ['required', 'boolean'],
            'auto_send_orders' => ['required', 'boolean'],

            'token_action' => [
                Rule::requiredIf(fn (): bool => $this->boolean('receive_orders')),
                'nullable',
                Rule::in($isUpdate
                    ? ['keep', 'generate', 'manual']
                    : ['generate', 'manual']),
            ],
            'api_token' => [
                Rule::requiredIf(fn (): bool =>
                    $this->boolean('receive_orders')
                    && $this->input('token_action') === 'manual'
                ),
                'nullable',
                'string',
                'min:32',
                'max:2000',
            ],

            'remote_order_endpoint' => [
                Rule::requiredIf(fn (): bool => $this->boolean('send_orders')),
                'nullable',
                'url:http,https',
                'max:2000',
            ],
            'remote_health_endpoint' => [
                'nullable',
                'url:http,https',
                'max:2000',
            ],
            'remote_api_token' => [
                Rule::requiredIf(fn (): bool => $this->boolean('send_orders')
                    && ! $remoteTokenAlreadySaved),
                'nullable',
                'string',
                'min:32',
                'max:2000',
            ],
            'request_timeout' => ['required', 'integer', 'min:3', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.url' => 'Website domain must be a valid HTTP or HTTPS URL.',
            'domain.unique' => 'This website domain has already been added.',
            'token_action.required' => 'Please select how the receiver token will be configured when Receive Orders is enabled.',
            'api_token.required' => 'Enter this website\'s receiver token when manual receiver-token setup is selected.',
            'api_token.min' => 'Receiver token must contain at least 32 characters.',
            'remote_order_endpoint.required' => 'Enter the external website receiver endpoint when Send Orders is enabled.',
            'remote_order_endpoint.url' => 'External receiver endpoint must be a valid HTTP or HTTPS URL.',
            'remote_health_endpoint.url' => 'External health endpoint must be a valid HTTP or HTTPS URL.',
            'remote_api_token.required' => 'Enter the token generated by the external website for outgoing orders.',
            'remote_api_token.min' => 'External receiver token must contain at least 32 characters.',
            'request_timeout.min' => 'Request timeout must be at least 3 seconds.',
            'request_timeout.max' => 'Request timeout cannot exceed 120 seconds.',
        ];
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }
}
