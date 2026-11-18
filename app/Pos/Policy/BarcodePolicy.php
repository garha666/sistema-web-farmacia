<?php

declare(strict_types=1);

namespace App\Pos\Policy;

/**
 * Código de barras EAN-13 (RF-71).
 *
 * Función pura: trece dígitos con dígito verificador correcto. Un producto
 * puede quedar sin código, por eso `null`, vacío y sólo espacios son válidos.
 */
final class BarcodePolicy
{
    public function isValid(?string $barcode): bool
    {
        $code = trim((string) $barcode);

        if ($code === '') {
            return true;
        }

        if (preg_match('/^\d{13}$/', $code) !== 1) {
            return false;
        }

        return $this->checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /**
     * Dígito verificador de los primeros doce dígitos del EAN-13.
     */
    public function checkDigit(string $twelveDigits): int
    {
        $sum = 0;

        foreach (str_split($twelveDigits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
