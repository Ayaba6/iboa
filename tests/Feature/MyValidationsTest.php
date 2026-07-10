<?php

/**
 * [CDC §Workflow] Page « Mes validations » + relances automatiques.
 */

use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ValidationStepNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

uses(\Tests\Concerns\RefreshDatabase::class);

function mvCompany(): Company
{
    $fy = FiscalYear::firstOrCreate(
        ['label' => 'MV-2026'],
        ['starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'ouvert', 'is_current' => true]
    );
    return Company::firstOrCreate(['name' => 'MV Co'], ['email' => 'mv@iboa.test', 'current_fiscal_year_id' => $fy->id]);
}

function mvUser(string $role): User
{
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $u = User::factory()->create(['company_id' => mvCompany()->id, 'email_verified_at' => now(), 'is_active' => true]);
    $u->assignRole($role);
    return $u;
}

function mvPendingOrder(int $hoursAgo = 0): Order
{
    $co = mvCompany();
    return Order::create([
        'company_id'     => $co->id,
        'fiscal_year_id' => $co->current_fiscal_year_id,
        'client_id'      => Client::factory()->create(['is_active' => true])->id,
        'number'         => 'CMD-MV-' . uniqid(),
        'status'         => 'en_attente_validation',
        'issued_at'      => now(),
        'total_ttc'      => 250_000,
        'submitted_at'   => now()->subHours($hoursAgo),
    ]);
}

it('affiche à un comptable les commandes en attente de validation', function () {
    $comptable = mvUser('comptable');
    $order     = mvPendingOrder();

    $this->actingAs($comptable)
        ->get(route('validations.index'))
        ->assertOk()
        ->assertSee($order->number)
        ->assertSee('En attente de ma validation');
});

it('ne montre pas les commandes à un opérateur sans droit de validation', function () {
    $operateur = mvUser('operateur_production');
    $order     = mvPendingOrder();

    $this->actingAs($operateur)
        ->get(route('validations.index'))
        ->assertOk()
        ->assertDontSee($order->number);
});

it('marque en retard les documents en attente depuis plus de 48h', function () {
    $comptable = mvUser('comptable');
    mvPendingOrder(hoursAgo: 72);

    $this->actingAs($comptable)
        ->get(route('validations.index'))
        ->assertOk()
        ->assertSee('retard');
});

it('relance les valideurs des documents en attente au-delà du délai', function () {
    Notification::fake();
    $comptable = mvUser('comptable'); // rôle cible des relances commandes
    $daf       = mvUser('daf');

    mvPendingOrder(hoursAgo: 72);  // > 48h → relance
    mvPendingOrder(hoursAgo: 2);   // récent → pas de relance

    Artisan::call('validations:remind');

    Notification::assertSentTo($comptable, ValidationStepNotification::class, function ($n) {
        return $n->type === 'validation_reminder';
    });
    // Un seul document relancé (le récent est ignoré)
    Notification::assertSentToTimes($comptable, ValidationStepNotification::class, 1);
});

it('le dry-run ne notifie personne', function () {
    Notification::fake();
    mvUser('comptable');
    mvPendingOrder(hoursAgo: 72);

    Artisan::call('validations:remind', ['--dry-run' => true]);

    Notification::assertNothingSent();
});

it('filtre par recherche, retard et montant minimum', function () {
    $comptable = mvUser('comptable');
    $recent = mvPendingOrder(hoursAgo: 2);   // 250 000 F
    $late   = mvPendingOrder(hoursAgo: 72);  // 250 000 F, en retard

    // Recherche par numéro : seul le document cherché apparaît
    $this->actingAs($comptable)
        ->get(route('validations.index', ['search' => $recent->number]))
        ->assertOk()
        ->assertSee($recent->number)
        ->assertDontSee($late->number);

    // Retard uniquement : le récent disparaît
    $this->actingAs($comptable)
        ->get(route('validations.index', ['late' => '1']))
        ->assertOk()
        ->assertSee($late->number)
        ->assertDontSee($recent->number);

    // Montant min au-dessus : plus rien
    $this->actingAs($comptable)
        ->get(route('validations.index', ['amount_min' => 500_000]))
        ->assertOk()
        ->assertDontSee($recent->number)
        ->assertDontSee($late->number);
});

it('cloche : reflète les documents en attente et les retire une fois traités', function () {
    $comptable = mvUser('comptable');
    $order     = mvPendingOrder();

    // 1. Le document en attente apparaît dans la cloche, badge = compte réel
    $json = $this->actingAs($comptable)->getJson(route('notifications.recent'))->assertOk()->json();
    expect($json['unread'])->toBe(1)
        ->and(collect($json['items'])->pluck('title')->first())->toContain($order->number)
        ->and(collect($json['items'])->first()['url'])->toContain('/ventes/commandes/');

    // 2. Reconsulter ne fait PAS disparaître la notification (pas de « lu »)
    $json2 = $this->actingAs($comptable)->getJson(route('notifications.recent'))->json();
    expect($json2['unread'])->toBe(1);

    // 3. Document validé → retiré automatiquement de la cloche
    $order->update(['status' => 'confirme']);
    $json3 = $this->actingAs($comptable)->getJson(route('notifications.recent'))->json();
    expect($json3['unread'])->toBe(0)
        ->and($json3['items'])->toBeEmpty();
});

it('cloche : chaque profil ne voit que son niveau de validation', function () {
    $comptable = mvUser('comptable');
    $operateur = mvUser('operateur_production');
    mvPendingOrder();

    expect($this->actingAs($comptable)->getJson(route('notifications.recent'))->json('unread'))->toBe(1)
        ->and($this->actingAs($operateur)->getJson(route('notifications.recent'))->json('unread'))->toBe(0);
});

it('cloche : pas de doublon pour un même document', function () {
    $comptable = mvUser('comptable');
    $order = mvPendingOrder();

    $items = $this->actingAs($comptable)->getJson(route('notifications.recent'))->json('items');
    $titles = collect($items)->pluck('title');
    expect($titles->filter(fn ($t) => str_contains($t, $order->number)))->toHaveCount(1);
});

it('ajoute le canal email seulement si l\'utilisateur a activé la préférence', function () {
    $avecEmail = mvUser('comptable');
    $avecEmail->update(['notify_by_email' => true]);
    $sansEmail = mvUser('daf'); // notify_by_email = false (défaut)

    $notification = new ValidationStepNotification(
        'Test', 'Message test', 'http://test', 'Order', 1
    );

    expect($notification->via($avecEmail))->toBe(['database', 'mail'])
        ->and($notification->via($sansEmail))->toBe(['database']);
});

it('désactive globalement le canal email via la config', function () {
    config(['validation.email_channel' => false]);

    $user = mvUser('comptable');
    $user->update(['notify_by_email' => true]);

    $notification = new ValidationStepNotification(
        'Test', 'Message test', 'http://test', 'Order', 1
    );

    expect($notification->via($user))->toBe(['database']);
});

it('le mail contient le titre, le message et le lien d\'action', function () {
    $user = mvUser('comptable');

    $notification = new ValidationStepNotification(
        'Devis à valider', 'Devis DEV-2026-00016 soumis — validation requise.',
        'http://erp.test/ventes/devis/16', 'Quote', 16
    );

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('[A3-ERP] Devis à valider')
        ->and($mail->actionUrl)->toBe('http://erp.test/ventes/devis/16')
        ->and(implode(' ', $mail->introLines))->toContain('DEV-2026-00016');
});
