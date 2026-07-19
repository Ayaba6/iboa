<?php

namespace App\Support;

/**
 * [Ventes — tôles bac] Règle de conversion centralisée et unique.
 *
 * Pour une tôle fabriquée sur mesure, le client saisit un NOMBRE de tôles et
 * une LONGUEUR unitaire (m). Le métrage linéaire total pilote le prix, la
 * matière, la production, la livraison :
 *
 *     Métrage linéaire total = Nombre de tôles × Longueur unitaire
 *
 * Le nombre de tôles et la longueur unitaire ne sont JAMAIS remplacés par le
 * métrage : ils restent distincts sur tous les documents. L'arrondi est
 * appliqué ici (jamais côté navigateur) pour une valeur unique et cohérente
 * du devis à la facture.
 */
final class SheetConversion
{
    /** Décimales de l'arrondi du métrage linéaire (mètres). */
    public const SCALE = 2;

    /**
     * Métrage linéaire total à partir du nombre de tôles et de la longueur
     * unitaire. Retourne null si l'un des deux n'est pas une tôle mesurée
     * (article standard → l'appelant garde la quantité saisie).
     */
    public static function linearMeters(mixed $sheetCount, mixed $unitLength): ?float
    {
        $n = (float) ($sheetCount ?? 0);
        $l = (float) ($unitLength ?? 0);

        if ($n <= 0 || $l <= 0) {
            return null;
        }

        return round($n * $l, self::SCALE);
    }

    /**
     * Quantité facturable d'une ligne : le métrage total pour une tôle mesurée,
     * sinon la quantité saisie (article standard, fallback 1).
     */
    public static function resolveQuantity(mixed $sheetCount, mixed $unitLength, mixed $fallbackQty = 1): float
    {
        return self::linearMeters($sheetCount, $unitLength)
            ?? (float) ($fallbackQty ?: 1);
    }

    /** Vrai si la ligne est une tôle mesurée (nombre ET longueur > 0). */
    public static function isMeasuredSheet(mixed $sheetCount, mixed $unitLength): bool
    {
        return (float) ($sheetCount ?? 0) > 0 && (float) ($unitLength ?? 0) > 0;
    }
}
