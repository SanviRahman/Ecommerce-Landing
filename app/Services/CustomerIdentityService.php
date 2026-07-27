<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CustomerIdentityService
{
    public function normalizePhone(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?: '';
    }

    public function normalizeName(?string $name): string
    {
        return Customer::normalizeName($name);
    }

    public function resolveOrCreate(
        string $name,
        string $phone,
        string $errorField = 'phone'
    ): Customer {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $phone = $this->normalizePhone($phone);
        $normalizedName = $this->normalizeName($name);

        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages([
                $errorField => 'Customer name is required and may not be greater than 255 characters.',
            ]);
        }

        if (! preg_match('/^01\d{9}$/', $phone)) {
            throw ValidationException::withMessages([
                $errorField => 'Phone number must contain exactly 11 local digits and start with 01.',
            ]);
        }

        $customer = Customer::query()
            ->where('phone', $phone)
            ->lockForUpdate()
            ->first();

        if ($customer) {
            $this->ensureSameCustomer($customer, $normalizedName, $errorField);

            return $customer;
        }

        try {
            return Customer::query()->create([
                'name'            => $name,
                'normalized_name' => $normalizedName,
                'phone'           => $phone,
            ]);
        } catch (QueryException $exception) {
            // Protect against two requests creating the same unique phone concurrently.
            $customer = Customer::query()
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                throw $exception;
            }

            $this->ensureSameCustomer($customer, $normalizedName, $errorField);

            return $customer;
        }
    }

    private function ensureSameCustomer(
        Customer $customer,
        string $normalizedName,
        string $errorField
    ): void {
        if ($customer->normalized_name === $normalizedName) {
            return;
        }

        throw ValidationException::withMessages([
            $errorField => sprintf(
                'Phone %s is already registered to customer "%s". A different customer cannot use this phone number.',
                $customer->phone,
                $customer->name
            ),
        ]);
    }
}
