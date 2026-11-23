<?php

namespace App\Sincronizacion;

/**
 * Normalizes identity documents so the same person or company is matched across programs.
 *
 * Returns null when the value is too poor to merge on safely: such records are kept,
 * but each one gets its own ficha.
 */
class Documento
{
    /**
     * Normalize a DNI, NIE or passport number.
     */
    public static function persona(?string $valor): ?string
    {
        $documento = self::limpiar($valor);

        if ($documento === null) {
            return null;
        }

        if (preg_match('/^(\d{1,8})([A-Z])$/', $documento, $partes)) {
            return str_pad($partes[1], 8, '0', STR_PAD_LEFT).$partes[2];
        }

        if (preg_match('/^([XYZ])(\d{1,7})([A-Z])$/', $documento, $partes)) {
            return $partes[1].str_pad($partes[2], 7, '0', STR_PAD_LEFT).$partes[3];
        }

        return self::esOtroDocumentoValido($documento) ? $documento : null;
    }

    /**
     * Normalize a company CIF (or the NIF of a self-employed person).
     */
    public static function empresa(?string $valor): ?string
    {
        $documento = self::limpiar($valor);

        if ($documento === null) {
            return null;
        }

        if (preg_match('/^[A-HJ-NP-SUVW]\d{7}[0-9A-J]$/', $documento)) {
            return $documento;
        }

        return self::persona($documento);
    }

    private static function limpiar(?string $valor): ?string
    {
        $documento = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim((string) $valor)));

        if ($documento === '' || preg_match('/^0+[A-Z]?$/', $documento)) {
            return null;
        }

        return $documento;
    }

    /**
     * Passports and other foreign documents: long enough and with digits.
     */
    private static function esOtroDocumentoValido(string $documento): bool
    {
        $longitud = strlen($documento);

        return $longitud >= 6 && $longitud <= 20 && preg_match('/\d/', $documento) === 1;
    }
}
