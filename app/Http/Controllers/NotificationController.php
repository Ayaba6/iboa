<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * [CDC §Workflow] Types de notification « en attente de validation » —
     * uniquement les DEMANDES D'ACTION adressées au valideur. Les retours
     * (document validé, refusé, clôturé…) restent sur /notifications.
     */
    private const PENDING_VALIDATION_TYPES = [
        'workflow_validation',          // générique
        'quote_submitted',
        'order_submitted',
        'delivery_note_submitted',
        'invoice_submitted',
        'credit_note_submitted',
        'of_submitted',                 // §13.3 — validation chef/responsable
        'of_financial_gate_blocked',    // §13.2 — autorisation DAF requise
        'of_modification_step',         // §13.10 — avis à donner
        'purchase_request_submitted',   // §13.4
        'po_submitted_approval',
        'output_declared',              // §13.3 — visa chef d'équipe
        'waste_declared',               // §13.9 — analyse chef atelier
        'maintenance_requested',        // §13.8
        'non_conformity_opened',
        'credit_limit_exceeded',
        'validation_reminder',          // relances
    ];

    public function index(): View
    {
        $notifications = Auth::user()
            ->notifications()
            ->paginate(20);

        $unreadCount = Auth::user()->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * Return unread count + recent 8 notifications for the header bell (AJAX).
     *
     * [PERF-FIX-04] Was 2 queries (list + count). Now 1 query: derive the unread
     * count from the fetched collection. If ALL 8 fetched rows are unread it is
     * possible the real count is higher, so we fire a cheap COUNT() only in that
     * edge case (stays ≤ 2 queries in the worst case, usually 1).
     */
    /**
     * [CDC §Workflow] La cloche reflète l'ÉTAT RÉEL : tous les documents
     * « en attente de validation » qui concernent l'utilisateur (selon ses
     * permissions), tant qu'ils ne sont pas validés/refusés/annulés.
     * Aucune notion de « lu » : un document en attente reste affiché, un
     * document traité disparaît automatiquement au refresh suivant.
     */
    public function recent(\App\Services\PendingValidationsService $pendingValidations): JsonResponse
    {
        $user    = Auth::user();
        $pending = $pendingValidations->for($user)
            ->sortByDesc(fn ($r) => $r['submitted_at']?->timestamp ?? 0)
            ->values();

        $items = $pending->take(8)->map(fn ($r) => [
            'id'         => md5($r['type'] . $r['number'] . $r['level']),
            'read'       => false, // toujours actif tant que le document est en attente
            'type'       => 'pending_validation',
            'icon'       => 'clipboard-document-check',
            'color'      => $r['is_late'] ? 'red' : 'amber',
            'title'      => $r['type'] . ' ' . $r['number'] . ($r['is_late'] ? ' ⏰' : ''),
            'message'    => implode(' · ', array_filter([
                $r['level'],
                $r['tiers'] ? 'client ' . $r['tiers'] : null,
                $r['amount'] ? number_format($r['amount'], 0, ',', ' ') . ' F' : null,
                $r['requester'] ? 'par ' . $r['requester'] : null,
            ])),
            'url'        => $r['url'],
            'created_at' => $r['submitted_at']?->diffForHumans() ?? '',
        ])->values();

        return response()->json([
            'unread' => $pending->count(), // badge = nombre réel de documents en attente
            'items'  => $items,
        ]);
    }

    /**
     * Mark one notification as read and redirect to its URL.
     */
    public function markRead(string $id): RedirectResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard');
        return redirect($url);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $query = Auth::user()->unreadNotifications();

        // Depuis la cloche (dédiée aux demandes en attente de validation),
        // ne marquer que celles-ci — l'historique complet garde ses non-lues.
        if ($request->input('scope') === 'validations') {
            $query->where('type', \App\Notifications\ValidationStepNotification::class)
                  ->whereIn('data->type', self::PENDING_VALIDATION_TYPES);
        }

        $query->get()->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }
}
