<?php

if (!function_exists('formaterNumeroAlgerien')) {
    function formaterNumeroAlgerien(string $numero): ?string
    {
        $numero = preg_replace('/[^0-9+]/', '', $numero);

        if ($numero === '') {
            return null;
        }

        if (str_starts_with($numero, '00')) {
            $numero = '+' . substr($numero, 2);
        }

        if (str_starts_with($numero, '+213') && strlen($numero) === 13) {
            return $numero;
        }

        if (str_starts_with($numero, '213') && strlen($numero) === 12) {
            return '+' . $numero;
        }

        if (str_starts_with($numero, '0') && strlen($numero) === 10) {
            return '+213' . substr($numero, 1);
        }

        if (strlen($numero) === 9 && preg_match('/^[567]/', $numero)) {
            return '+213' . $numero;
        }

        if (str_starts_with($numero, '+213') && strlen($numero) === 12) {
            return $numero;
        }

        return null;
    }
}
