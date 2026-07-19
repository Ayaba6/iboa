<?php

namespace App\Support\Excel;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * [Sécurité — injection de formule CSV/Excel]
 *
 * Une valeur texte commençant par « = », « + », « - » ou « @ » est interprétée
 * comme une FORMULE par Excel/LibreOffice à l'ouverture du fichier exporté. Une
 * donnée tierce contrôlée par un utilisateur (nom de client, référence, note…)
 * du type `=HYPERLINK("http://evil","clic")` ou `=cmd|'/c calc'!A1` s'exécute
 * alors sur le poste de la personne qui ouvre l'export.
 *
 * Ce binder force en TEXTE toute chaîne débutant par un de ces caractères, en
 * la préfixant d'une apostrophe (convention tableur : « texte littéral »). Les
 * nombres, dates et booléens ne sont pas des `string` ici et passent au binder
 * par défaut → typage normal préservé (montants, quantités inchangés).
 *
 * Câblé globalement dans config/excel.php (value_binder.default).
 */
class AntiInjectionValueBinder extends DefaultValueBinder
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && $value !== '' && in_array($value[0], self::DANGEROUS_PREFIXES, true)) {
            $cell->setValueExplicit("'" . $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
