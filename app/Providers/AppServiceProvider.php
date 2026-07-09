<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\ClientPayment;
use App\Models\DeliveryNote;
use App\Models\InventorySession;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\ClientPaymentPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\DeliveryNotePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\InventorySessionPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PayrollRunPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RolePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPaymentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Repositories\CompanyRepository;
use App\Repositories\ProductRepository;
use App\Events\CreditNoteValidated;
use App\Events\InvoiceValidated;
use App\Events\OrderConfirmed;
use App\Events\PaymentReceived;
use App\Events\StockAlertTriggered;
use App\Events\SupplierInvoiceValidated;
use App\Events\SupplierPaymentCreated;
use App\Listeners\NotifyLowStock;
use App\Listeners\NotifyCreditNoteValidated;
use App\Listeners\NotifySupplierInvoiceValidated;
use App\Listeners\ReserveStockOnOrderConfirmed;
use App\Listeners\TriggerMtoProductionOnOrderConfirmed;
use App\Listeners\SendInvoiceToClient;
use App\Listeners\SyncClientBalanceOnInvoice;
use App\Listeners\SyncSupplierBalanceOnInvoice;
use App\Listeners\UpdateClientBalance;
use App\Listeners\UpdateSupplierBalance;
use App\Services\UserHomeRoute;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CompanyRepository::class, fn() => new CompanyRepository(new \App\Models\Company));
        $this->app->bind(ProductRepository::class, fn() => new ProductRepository(new \App\Models\Product));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Rate limiters API ──────────────────────────────────────────────────
        // [SEC-API] Rate limiter nommé "api" :
        //   • 60 req/min par utilisateur authentifié (clé = user_id)
        //   • 30 req/min par adresse IP pour les routes non authentifiées (fallback)
        RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Rate limiter strict pour les endpoints d'authentification (brute-force)
        RateLimiter::for('api_auth', function (\Illuminate\Http\Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Rate limiter webhooks paiement mobile (Orange Money / Moov Money)
        // Les providers légitimes envoient rarement > 60 callbacks/min par IP.
        // Limite 120/min pour absorber les rafales, 429 sinon (toujours JSON).
        RateLimiter::for('webhooks', function (\Illuminate\Http\Request $request) {
            return Limit::perMinute(120)->by($request->ip())
                ->response(function () {
                    return response()->json(
                        ['status' => 'rate_limited', 'retry_after' => 60],
                        429
                    );
                });
        });

        // ── Event → Listener bindings ──────────────────────────────────────────
        // Commandes
        Event::listen(OrderConfirmed::class,            ReserveStockOnOrderConfirmed::class);
        Event::listen(OrderConfirmed::class,            TriggerMtoProductionOnOrderConfirmed::class);

        // Ventes
        Event::listen(InvoiceValidated::class,          SendInvoiceToClient::class);
        Event::listen(InvoiceValidated::class,          SyncClientBalanceOnInvoice::class);
        Event::listen(PaymentReceived::class,            UpdateClientBalance::class);

        // Achats
        Event::listen(SupplierInvoiceValidated::class,  NotifySupplierInvoiceValidated::class);
        Event::listen(SupplierInvoiceValidated::class,  SyncSupplierBalanceOnInvoice::class);
        Event::listen(SupplierPaymentCreated::class,     UpdateSupplierBalance::class);

        // Avoirs
        Event::listen(CreditNoteValidated::class,        NotifyCreditNoteValidated::class);

        // Stock
        Event::listen(StockAlertTriggered::class,        NotifyLowStock::class);

        // [Sync ERP] Les échecs de synchronisation détectés en transaction sont
        // écrits APRÈS le rollback — sinon le log serait effacé avec la transaction.
        Event::listen(\Illuminate\Database\Events\TransactionRolledBack::class, function () {
            if (\Illuminate\Support\Facades\DB::transactionLevel() === 0) {
                \App\Services\Sync\SyncLogger::flushDeferredFailures();
            }
        });

        // Note: GL posting (AccountingService) is called DIRECTLY inside each
        // service transaction — not via listeners — to guarantee atomicity.

        // ── Rediriger les users déjà connectés vers leur module principal ────────
        RedirectIfAuthenticated::redirectUsing(fn () => UserHomeRoute::resolve());

        // ── Super admin bypasses all policy checks ─────────────────────────────
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // Register model policies
        Gate::policy(Product::class,         ProductPolicy::class);
        Gate::policy(Client::class,          ClientPolicy::class);
        Gate::policy(Supplier::class,        SupplierPolicy::class);
        Gate::policy(\App\Models\Quote::class,          \App\Policies\QuotePolicy::class);
        Gate::policy(Order::class,           OrderPolicy::class);
        Gate::policy(Invoice::class,         InvoicePolicy::class);
        Gate::policy(DeliveryNote::class,    DeliveryNotePolicy::class);
        Gate::policy(CreditNote::class,      CreditNotePolicy::class);
        Gate::policy(PurchaseOrder::class,   PurchaseOrderPolicy::class);
        Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class);
        Gate::policy(StockMovement::class,   StockMovementPolicy::class);
        Gate::policy(InventorySession::class,InventorySessionPolicy::class);
        Gate::policy(ClientPayment::class,   ClientPaymentPolicy::class);
        Gate::policy(User::class,            UserPolicy::class);
        Gate::policy(Role::class,            RolePolicy::class);
        Gate::policy(AuditLog::class,        AuditLogPolicy::class);
        // [SEC-PHASE2] AttachmentPolicy avec methodes personnalisees (viewAttachmentsOf, create, download, delete)
        Gate::policy(\App\Models\Attachment::class, \App\Policies\AttachmentPolicy::class);

        // [AUDIT-POL] Policies manquantes — entités financières et RH critiques
        Gate::policy(\App\Models\JournalEntry::class,   JournalEntryPolicy::class);
        Gate::policy(\App\Models\Employee::class,       EmployeePolicy::class);
        Gate::policy(\App\Models\PayrollRun::class,     PayrollRunPolicy::class);
        Gate::policy(\App\Models\SupplierPayment::class, SupplierPaymentPolicy::class);
        Gate::policy(\App\Models\SalesRep::class, \App\Policies\SalesRepPolicy::class);
        Gate::policy(\App\Models\Commission::class, \App\Policies\SalesRepPolicy::class);

        // [MED-4] Observers d'audit — trace dans audit_logs les opérations critiques.
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);

        // [AUDIT-B] Observers pour Stock + Compta + Trésorerie
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);
        \App\Models\JournalEntry::observe(\App\Observers\JournalEntryObserver::class);
        \App\Models\ClientPayment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\SupplierPayment::observe(\App\Observers\PaymentObserver::class);

        // Calcul automatique des commissions sur encaissements clients
        \App\Models\ClientPayment::observe(\App\Observers\CommissionObserver::class);

        // [AUDIT-ERP-D] Morph map pour StockMovement::referenceable()
        // Permet la résolution polymorphique sans utiliser le FQCN comme discriminant.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'delivery_note'     => \App\Models\DeliveryNote::class,
            'reception'         => \App\Models\Reception::class,
            'invoice'           => \App\Models\Invoice::class,
            'supplier_invoice'  => \App\Models\SupplierInvoice::class,
            'credit_note'       => \App\Models\CreditNote::class,
            'inventory_session' => \App\Models\InventorySession::class,
            'stock_transfer'    => \App\Models\StockTransfer::class,
            'adjustment'        => \App\Models\StockMovement::class,
        ]);

        // [TRACE] Observers étendus — traçabilité complète des entités métier critiques.
        \App\Models\Quote::observe(\App\Observers\QuoteObserver::class);
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\PurchaseOrder::observe(\App\Observers\PurchaseOrderObserver::class);
        \App\Models\SupplierInvoice::observe(\App\Observers\SupplierInvoiceObserver::class);
        \App\Models\StockTransfer::observe(\App\Observers\StockTransferObserver::class);
        \App\Models\Rfq::observe(\App\Observers\RfqObserver::class);
    }
}
