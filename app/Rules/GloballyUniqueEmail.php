<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class GloballyUniqueEmail implements ValidationRule
{
    public function __construct(
        private ?string $ignoreTable = null,
        private ?int $ignoreId = null
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim((string) $value));

        if ($email === '') {
            return;
        }

        foreach (['users', 'customers'] as $table) {
            $query = DB::table($table)->whereRaw('LOWER(email) = ?', [$email]);

            if ($this->ignoreTable === $table && $this->ignoreId) {
                $query->where('id', '!=', $this->ignoreId);
            }

            if ($query->exists()) {
                $fail('Este correo ya esta registrado en el sistema.');

                return;
            }
        }
    }
}
