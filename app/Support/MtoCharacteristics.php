<?php

namespace App\Support;

/**
 * [BUG-A3-MTO-TECH-010] Caractéristiques techniques d'une ligne MTO — liste unique.
 *
 * Elles voyagent du devis à la commande, puis à l'ordre de fabrication, sous les
 * MÊMES noms partout : la propagation est une recopie, jamais une traduction.
 * Une liste écrite trois fois finit par diverger d'un champ, et le champ perdu
 * est silencieux — c'est exactement ce qui s'est produit sur les conditions de
 * paiement à la conversion (BUG-A3-SALES-CONV-002).
 *
 * Ces valeurs décrivent ce que le CLIENT a demandé. Elles ne se déduisent ni du
 * libellé de l'article, ni de sa nomenclature : « Tôle bac beige 27/100 » écrit
 * dans une désignation ne prouve ni la couleur, ni l'épaisseur, et rien ne
 * garantit que le texte et la valeur structurée concordent.
 */
final class MtoCharacteristics
{
    /** Champs portés à l'identique par quote_items, order_items et production_orders. */
    public const CHAMPS = [
        'sheet_type',
        'color',
        'couleur_ral',
        'revetement',
        'profil',
        'nb_ondes',
        'thickness',
        'usable_width',
        'largeur_totale',
        'tolerance_longueur',
        'tolerance_epaisseur',
    ];

    /**
     * Extrait les caractéristiques d'un tableau de saisie ou d'un modèle.
     *
     * Une valeur absente reste `null` : elle n'est pas inventée, et surtout pas
     * remplacée par un défaut d'article ou de nomenclature. Une caractéristique
     * non saisie doit rester visiblement vide plutôt que d'être remplie par une
     * valeur plausible que personne n'a demandée.
     */
    public static function extraire(mixed $source): array
    {
        $lire = static function (string $champ) use ($source) {
            if (is_array($source)) {
                return $source[$champ] ?? null;
            }

            return is_object($source) ? ($source->{$champ} ?? null) : null;
        };

        $valeurs = [];
        foreach (self::CHAMPS as $champ) {
            $v = $lire($champ);
            $valeurs[$champ] = ($v === '' ? null : $v);
        }

        return $valeurs;
    }

    /** Une ligne porte-t-elle au moins une caractéristique renseignée ? */
    public static function renseignees(mixed $source): bool
    {
        return collect(self::extraire($source))->filter(fn ($v) => $v !== null)->isNotEmpty();
    }
}
