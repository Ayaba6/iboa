<?php

namespace App\Support;

/**
 * [ACHATS A1] Politique de normalisation du numéro de facture fournisseur pour
 * la détection de doublons — DOCUMENTÉE et volontairement PRUDENTE (ne jamais
 * fusionner deux références réellement distinctes).
 *
 * Règles appliquées (dans l'ordre) :
 *   1. suppression des caractères invisibles / de contrôle (zero-width, BOM,
 *      soft-hyphen, word-joiner) — insérés par copier-coller ;
 *   2. unification des tirets Unicode (‐ ‑ ‒ – — ― et signe moins) vers le
 *      tiret ASCII « - » ;
 *   3. trim + réduction des espaces internes multiples à un seul espace ;
 *   4. passage en MAJUSCULES (insensible à la casse).
 *
 * NON appliqué volontairement (risque de fusion de références distinctes) :
 *   - la conversion espace ↔ tiret : « FAC 2026 001 » et « FAC-2026-001 »
 *     RESTENT distincts pour la règle comptable stricte. Une éventuelle collision
 *     espace/tiret relève de la détection documentaire (suspicion), pas du blocage.
 *
 * Exemples fusionnés (doublons) :
 *   "FAC-2026-001" = "fac-2026-001" = " FAC-2026-001 " = "FAC–2026–001" (en-dash)
 *   = "FAC-2026-001\u{200B}" (zero-width) → "FAC-2026-001".
 */
class SupplierInvoiceNumber
{
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = $raw;
        // 1. caractères invisibles / de contrôle
        $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}\x{00AD}\x{180E}]/u', '', $s) ?? $s;
        // 2. tirets Unicode → ASCII
        $s = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]/u', '-', $s) ?? $s;
        // 3. trim + espaces multiples → un espace
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? $s;
        // 4. majuscules (Unicode-safe)
        $s = mb_strtoupper($s, 'UTF-8');

        return $s === '' ? null : $s;
    }
}
