<?php

/**
 * Fixed-scale decimal arithmetic without binary floating point.
 */
class DkCanonicalDecimal
{
    public const SCALE = 8;

    public static function normalize($value)
    {
        $value = trim((string) $value);

        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw new InvalidArgumentException('Invalid decimal amount');
        }

        $sign = $matches[1];
        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = isset($matches[3]) ? $matches[3] : '';

        if (strlen($fraction) > self::SCALE) {
            throw new InvalidArgumentException('Decimal amount exceeds canonical scale');
        }

        $fraction = str_pad($fraction, self::SCALE, '0');

        if ($integer === '0' && trim($fraction, '0') === '') {
            $sign = '';
        }

        return $sign.$integer.'.'.$fraction;
    }

    public static function add($left, $right)
    {
        [$leftSign, $leftDigits] = self::scaledParts($left);
        [$rightSign, $rightDigits] = self::scaledParts($right);

        if ($leftSign === $rightSign) {
            $digits = self::addUnsigned($leftDigits, $rightDigits);
            return self::fromScaled($leftSign, $digits);
        }

        $comparison = self::compareUnsigned($leftDigits, $rightDigits);
        if ($comparison === 0) {
            return self::normalize('0');
        }

        if ($comparison > 0) {
            return self::fromScaled($leftSign, self::subtractUnsigned($leftDigits, $rightDigits));
        }

        return self::fromScaled($rightSign, self::subtractUnsigned($rightDigits, $leftDigits));
    }

    public static function equals($left, $right)
    {
        return self::normalize($left) === self::normalize($right);
    }

    private static function scaledParts($value)
    {
        $normalized = self::normalize($value);
        $negative = $normalized[0] === '-';
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        $digits = str_replace('.', '', $normalized);
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        return array($negative ? -1 : 1, $digits);
    }

    private static function fromScaled($sign, $digits)
    {
        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;
        $digits = str_pad($digits, self::SCALE + 1, '0', STR_PAD_LEFT);

        $integer = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);
        $prefix = ($sign < 0 && trim($digits, '0') !== '') ? '-' : '';

        return $prefix.$integer.'.'.$fraction;
    }

    private static function compareUnsigned($left, $right)
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) < strlen($right) ? -1 : 1;
        }

        $comparison = strcmp($left, $right);
        return $comparison < 0 ? -1 : ($comparison > 0 ? 1 : 0);
    }

    private static function addUnsigned($left, $right)
    {
        $left = strrev($left);
        $right = strrev($right);
        $length = max(strlen($left), strlen($right));
        $carry = 0;
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $sum = ($i < strlen($left) ? (int) $left[$i] : 0)
                + ($i < strlen($right) ? (int) $right[$i] : 0)
                + $carry;

            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        if ($carry) {
            $result .= (string) $carry;
        }

        return strrev($result);
    }

    private static function subtractUnsigned($left, $right)
    {
        // Precondition: left >= right.
        $left = strrev($left);
        $right = strrev($right);
        $borrow = 0;
        $result = '';

        for ($i = 0; $i < strlen($left); $i++) {
            $digit = (int) $left[$i] - $borrow - ($i < strlen($right) ? (int) $right[$i] : 0);
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result .= (string) $digit;
        }

        return strrev(rtrim($result, '0')) ?: '0';
    }
}
