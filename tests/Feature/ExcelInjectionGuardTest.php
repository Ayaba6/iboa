<?php

use App\Support\Excel\AntiInjectionValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * [Sécurité] Le value binder neutralise l'injection de formule dans les exports
 * Excel/CSV sans altérer les nombres et textes légitimes.
 */
function bindCell(string $ref, $value): \PhpOffice\PhpSpreadsheet\Cell\Cell
{
    $sheet = (new Spreadsheet())->getActiveSheet();
    $sheet->getCell($ref); // crée la cellule
    $binder = new AntiInjectionValueBinder();
    $cell = $sheet->getCell($ref);
    $binder->bindValue($cell, $value);

    return $cell;
}

it('force en texte les valeurs commençant par un caractère de formule', function (string $payload) {
    $cell = bindCell('A1', $payload);

    // La valeur stockée est préfixée d'apostrophe → littéral, non exécutée.
    expect($cell->getValue())->toBe("'" . $payload)
        ->and($cell->getDataType())->toBe(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
})->with([
    '=HYPERLINK("http://evil","clic")',
    '=cmd|\'/c calc\'!A1',
    '+1+1',
    '-2+3',
    '@SUM(A1:A9)',
]);

it('ne touche pas les nombres ni les textes légitimes', function () {
    // Montant négatif = float, pas string → typage numérique préservé.
    $neg = bindCell('A2', -1500.0);
    expect($neg->getValue())->toBe(-1500.0);

    $amount = bindCell('A3', 111392);
    expect($amount->getValue())->toBe(111392);

    $name = bindCell('A4', 'SOCIETE TEST BF SARL');
    expect($name->getValue())->toBe('SOCIETE TEST BF SARL');

    // Un nom légitime avec tiret interne n'est pas altéré (ne commence pas par -).
    $ref = bindCell('A5', 'FA-2026-020');
    expect($ref->getValue())->toBe('FA-2026-020');
});
