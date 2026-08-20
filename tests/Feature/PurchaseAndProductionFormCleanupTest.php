<?php

/**
 * [Achats / Production] Doublons et exemples trompeurs retirés des formulaires.
 *
 * Le même balayage que sur les écrans de vente, appliqué aux six formulaires
 * restants. Défauts corrigés :
 *
 *  1. « Type de document » en lecture seule — répétait le titre de la page sur un
 *     écran où il ne peut rien valoir d'autre, avec un astérisque rouge dépourvu de
 *     sens sur un champ non saisissable.
 *  2. Le numéro auto en lecture seule — répétait le titre et la barre d'état basse.
 *     Le badge de statut qu'il hébergeait est conservé : c'était la seule
 *     information non redondante du bloc.
 *  3. Le « XOF » en lecture seule accolé au taux de change — écho du champ Devise,
 *     figé sur XOF même après changement de devise.
 *  4. Le libellé « Prix / Devise » — le sélecteur ne porte que le mode de prix.
 *  5. Le champ « Taxes » (`default_tax_label`) sur la facture fournisseur — libellé
 *     stocké, validé, `fillable`, jamais lu. Désormais dérivé des lignes.
 *  6. Douze exemples lisibles comme des valeurs saisies, dont « TRANSPORT PLUS »,
 *     « 11 BF 2567 » et « Usine – Ouagadougou ».
 *  7. Sur l'OF, trois `<input hidden>` de repli doublaient un `<select>` de même nom.
 *
 * Distinction tenue : un placeholder qui DÉCRIT quoi saisir — « Réf. reçue du
 * fournisseur », « Banque du fournisseur » — n'est pas trompeur et reste tel quel.
 * Seules les valeurs FABRIQUÉES, qui se lisent comme une donnée déjà renseignée,
 * ont été préfixées.
 */

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function cleanupFixture(): array
{
    $fy = FiscalYear::firstOrCreate(['label' => 'CLEANUP-2026'], [
        'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true,
    ]);
    $company = Company::firstOrCreate(['name' => 'Cleanup Co'], [
        'email' => 'cleanup@oa-metal.test', 'current_fiscal_year_id' => $fy->id,
    ]);
    app()->instance('current_company', $company);

    $user = User::factory()->create(['company_id' => $company->id]);
    test()->actingAs($user);

    return compact('company', 'fy', 'user');
}

/**
 * Accorde les droits puis rend la page. Chaque écran a ses propres permissions ;
 * les accorder au coup par coup évite un super-utilisateur qui masquerait un
 * contrôle d'accès défaillant.
 *
 * @param  array<string,mixed>  $f
 * @param  list<string>  $abilities
 */
function cleanupHtml(array $f, string $route, string $suffix, array $abilities): string
{
    $role = Role::firstOrCreate(['name' => 'cleanup_'.$suffix, 'guard_name' => 'web']);
    foreach ($abilities as $ability) {
        $role->givePermissionTo(Permission::firstOrCreate(['name' => $ability, 'guard_name' => 'web']));
    }
    $f['user']->assignRole($role);

    return test()->get($route)->assertOk()->getContent();
}

// ── Les six écrans ───────────────────────────────────────────────────────────

dataset('ecrans', [
    'facture fournisseur' => ['/achats/factures-fournisseurs/create', 'fi', ['supplier_invoices.view', 'supplier_invoices.create']],
    'commande achat'      => ['/achats/commandes/create',             'po', ['purchase_orders.view', 'purchase_orders.create']],
    'demande achat'       => ['/achats/demandes-achat/create',        'pr', ['purchase_requests.view', 'purchase_requests.create']],
    'retour fournisseur'  => ['/achats/retours-fournisseurs/create',  'rf', ['supplier_returns.view', 'supplier_returns.create']],
]);

it('ne répète plus le type de document ni le numéro auto', function (string $route, string $suffix, array $abilities) {
    $html = cleanupHtml(cleanupFixture(), $route, $suffix, $abilities);

    expect($html)->not->toContain('Type de document')
        ->and($html)->not->toContain('Auto à la création')
        // Le statut, seule information non redondante du bloc, subsiste.
        ->and($html)->toContain('Statut');
})->with('ecrans');

it('ne présente plus aucune valeur fabriquée en exemple', function (string $route, string $suffix, array $abilities) {
    $html = cleanupHtml(cleanupFixture(), $route, $suffix, $abilities);

    // Ces chaînes se lisent comme des données déjà saisies : le nom d'un
    // transporteur, une immatriculation, une adresse de site, une référence projet.
    foreach (['TRANSPORT PLUS', '11 BF 2567', 'Usine – Ouagadougou', 'REF-2026-001', 'PROJ-2026-0008', 'Régime réel normal', 'Coris Bank International'] as $fabriquee) {
        expect($html)->not->toMatch('/placeholder="(?!Ex\. )[^"]*'.preg_quote($fabriquee, '/').'/u');
    }
})->with('ecrans');

// ── La facture fournisseur ───────────────────────────────────────────────────

it('n’expose plus de champ « Taxes » concurrent de la TVA des lignes', function () {
    $f = cleanupFixture();
    $html = cleanupHtml($f, '/achats/factures-fournisseurs/create', 'taxes', ['supplier_invoices.view', 'supplier_invoices.create']);

    expect($html)->not->toContain('default_tax_label')
        ->and($html)->not->toContain('Prix / Devise')
        ->and($html)->not->toMatch('/value="XOF"[^>]*readonly/');
});

it('CONSERVE « N° facture fournisseur » : c’est une saisie, pas un doublon', function () {
    // Le numéro propre du fournisseur est indispensable au rapprochement ; le
    // confondre avec le numéro interne auto-généré aurait fait perdre une donnée.
    $f = cleanupFixture();
    $html = cleanupHtml($f, '/achats/factures-fournisseurs/create', 'numfrn', ['supplier_invoices.view', 'supplier_invoices.create']);

    expect($html)->toContain('supplier_invoice_number')
        ->and($html)->toContain('N° facture fournisseur');
});

it('dérive le libellé de taxation par le service partagé, sans copie locale', function () {
    $source = file_get_contents(app_path('Services/SupplierInvoiceService.php'));

    expect($source)->toContain('SalesTaxLabelService::class)->derive')
        ->and($source)->not->toContain('private function deriveTaxLabel');
});

// ── L'ordre de fabrication ───────────────────────────────────────────────────

it('ne double plus les sélecteurs de gouvernance par un champ caché', function () {
    // Un `<input hidden>` de repli sert à une case à cocher décochée, jamais à un
    // `<select>` qui soumet TOUJOURS une valeur. Le hidden précédait le select, donc
    // PHP retenait bien le choix de l'utilisateur : ce n'était pas un bug, seulement
    // du balisage mort — vérifié avant retrait, pas supposé.
    $f = cleanupFixture();
    $html = cleanupHtml($f, '/production/orders/create', 'of', ['production.view', 'production.create']);

    foreach (['controle_qualite_obligatoire', 'autoriser_cloture_partielle', 'autoriser_depassement_qte'] as $champ) {
        preg_match_all('/name="'.$champ.'"/', $html, $m);
        expect(count($m[0]))->toBe(1, "Le champ {$champ} devrait apparaître une seule fois.");
    }
});

it('ne présente plus de valeur fabriquée en exemple sur l’OF', function () {
    $f = cleanupFixture();
    $html = cleanupHtml($f, '/production/orders/create', 'of2', ['production.view', 'production.create']);

    foreach (['Atelier de production', 'Équipe A', 'RAL 3000', 'Prélaqué 25 µm'] as $fabriquee) {
        expect($html)->not->toMatch('/placeholder="(?!Ex\. )[^"]*'.preg_quote($fabriquee, '/').'/u');
    }
});

// ── L'encaissement ───────────────────────────────────────────────────────────

it('ne répète plus le code devise sur l’encaissement', function () {
    $f = cleanupFixture();
    // Trois permissions, pas deux : le préfixe `tresorerie` tout entier est gardé par
    // `treasury.view`, en plus de `payments.view` sur le groupe et de `payments.create`
    // exigé par la policy. N'en accorder que deux renvoie 403 avant tout rendu.
    $html = cleanupHtml($f, '/tresorerie/encaissements/create', 'enc', ['treasury.view', 'payments.view', 'payments.create']);

    expect($html)->not->toMatch('/value="XOF"[^>]*readonly/');
});

// ── La frontière tenue ───────────────────────────────────────────────────────

it('conserve les exemples qui DÉCRIVENT quoi saisir', function () {
    // « Réf. reçue du fournisseur » et « Banque du fournisseur » ne se lisent pas
    // comme une donnée : ils indiquent quoi renseigner. Les préfixer par « Ex. : »
    // les aurait rendus absurdes. La règle porte sur les valeurs fabriquées, pas
    // sur les consignes.
    $f = cleanupFixture();
    $html = cleanupHtml($f, '/achats/factures-fournisseurs/create', 'consignes', ['supplier_invoices.view', 'supplier_invoices.create']);

    expect($html)->toContain('placeholder="Réf. reçue du fournisseur"')
        ->and($html)->toContain('placeholder="Banque du fournisseur"');
});
