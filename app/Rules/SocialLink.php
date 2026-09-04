<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SocialLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return;
        }

        if (preg_match('/^tel:\+?[0-9()\-\s]{3,40}$/i', $value) === 1) {
            return;
        }

        if (preg_match('/^sms:\+?[0-9()\-\s]{3,40}$/i', $value) === 1) {
            return;
        }

        if (preg_match('/^mailto:[^\s@]+@[^\s@]+\.[^\s@]+$/i', $value) === 1) {
            return;
        }

        $fail('The :attribute must be a valid http(s), tel:, sms:, or mailto: link.');
    }
}
