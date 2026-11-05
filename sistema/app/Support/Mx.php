<?php

namespace App\Support;

/** Formatos de México para documentos: importe con letra y teléfono legible. */
class Mx
{
    private const UNITS = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIÚN', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    private const TENS = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];

    private const HUNDREDS = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    /** 192997.85 → "CIENTO NOVENTA Y DOS MIL NOVECIENTOS NOVENTA Y SIETE PESOS 85/100 M.N." */
    public static function amountInWords(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        $pesos = intdiv($cents, 100);
        $unit = match (true) {
            $pesos === 1 => 'PESO',
            $pesos > 0 && $pesos % 1_000_000 === 0 => 'DE PESOS',
            default => 'PESOS',
        };

        return self::words($pesos).' '.$unit.sprintf(' %02d/100 M.N.', $cents % 100);
    }

    public static function words(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        $millions = intdiv($n, 1_000_000);
        $thousands = intdiv($n % 1_000_000, 1000);
        $rest = $n % 1000;
        $parts = [];
        if ($millions) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::words($millions).' MILLONES';
        }
        if ($thousands) {
            $parts[] = $thousands === 1 ? 'MIL' : self::belowThousand($thousands).' MIL';
        }
        if ($rest) {
            $parts[] = self::belowThousand($rest);
        }

        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $parts = [];
        if ($hundreds = intdiv($n, 100)) {
            $parts[] = self::HUNDREDS[$hundreds];
        }
        $r = $n % 100;
        if ($r && $r < 30) {
            $parts[] = self::UNITS[$r];
        } elseif ($r) {
            $parts[] = self::TENS[intdiv($r, 10)].($r % 10 ? ' Y '.self::UNITS[$r % 10] : '');
        }

        return implode(' ', $parts);
    }

    /** "525549114435" o "5549114435" → "55 4911 4435"; otros formatos se dejan como vienen. */
    public static function phone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) === 10 ? substr($digits, 0, 2).' '.substr($digits, 2, 4).' '.substr($digits, 6) : trim((string) $phone);
    }
}
