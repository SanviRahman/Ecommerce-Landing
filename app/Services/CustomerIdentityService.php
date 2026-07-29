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

        /*
         * A phone number is not a unique customer identity.
         * Different customers may share one phone number, so the stable
         * customer identity is normalized name + normalized phone.
         */
        $customer = Customer::query()
            ->where('normalized_name', $normalizedName)
            ->where('phone', $phone)
            ->lockForUpdate()
            ->first();

        if ($customer) {
            return $customer;
        }

        try {
            return Customer::query()->create([
                'name'            => $name,
                'normalized_name' => $normalizedName,
                'phone'           => $phone,
            ]);
        } catch (QueryException $exception) {
            /*
             * Protect against concurrent creation of the exact same customer
             * identity pair. A different name using the same phone remains valid.
             */
            $customer = Customer::query()
                ->where('normalized_name', $normalizedName)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if (! $customer) {
                throw $exception;
            }

            return $customer;
        }
    }
}
