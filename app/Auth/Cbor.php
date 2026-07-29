<?php

declare(strict_types=1);

namespace Katakata\Auth;

use RuntimeException;

final class Cbor
{
    /** @return mixed */
    public static function decode(string $bytes, int &$offset = 0)
    {
        if ($offset >= strlen($bytes)) {
            throw new RuntimeException('Unexpected end of CBOR data.');
        }

        $initial = ord($bytes[$offset++]);
        $major = $initial >> 5;
        $value = self::length($bytes, $offset, $initial & 31);

        return match ($major) {
            0 => $value,
            1 => -1 - $value,
            2 => self::take($bytes, $offset, $value),
            3 => self::take($bytes, $offset, $value),
            4 => self::array($bytes, $offset, $value),
            5 => self::map($bytes, $offset, $value),
            7 => match ($value) {
                20 => false,
                21 => true,
                22 => null,
                default => throw new RuntimeException('Unsupported CBOR simple value.'),
            },
            default => throw new RuntimeException('Unsupported CBOR value.'),
        };
    }

    private static function length(string $bytes, int &$offset, int $additional): int
    {
        if ($additional < 24) {
            return $additional;
        }

        $size = match ($additional) {
            24 => 1,
            25 => 2,
            26 => 4,
            default => throw new RuntimeException('Unsupported CBOR length.'),
        };
        $raw = self::take($bytes, $offset, $size);

        return match ($size) {
            1 => ord($raw),
            2 => unpack('n', $raw)[1],
            4 => unpack('N', $raw)[1],
        };
    }

    private static function take(string $bytes, int &$offset, int $length): string
    {
        if ($length < 0 || $offset + $length > strlen($bytes)) {
            throw new RuntimeException('Truncated CBOR data.');
        }

        $value = substr($bytes, $offset, $length);
        $offset += $length;
        return $value;
    }

    /** @return list<mixed> */
    private static function array(string $bytes, int &$offset, int $length): array
    {
        $result = [];
        for ($index = 0; $index < $length; $index++) {
            $result[] = self::decode($bytes, $offset);
        }
        return $result;
    }

    /** @return array<int|string, mixed> */
    private static function map(string $bytes, int &$offset, int $length): array
    {
        $result = [];
        for ($index = 0; $index < $length; $index++) {
            $key = self::decode($bytes, $offset);
            if (!is_int($key) && !is_string($key)) {
                throw new RuntimeException('Invalid CBOR map key.');
            }
            $result[$key] = self::decode($bytes, $offset);
        }
        return $result;
    }
}
