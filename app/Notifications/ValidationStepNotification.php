<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * [CDC §13] Notification générique pour les étapes de validation des
 * workflows métier (vente→production, OF, achats, modification OF, rebuts).
 *
 * Réutilisable plutôt qu'une classe par workflow — le contenu varie, la
 * forme (titre/message/url/lien vers le document) est toujours la même.
 * Le ciblage par rôle exact (qui doit agir, pas "tout le monde") se fait
 * via sendToRoles() — jamais de notification large/diffusée.
 */
class ValidationStepNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
        public readonly string $modelType,
        public readonly int $modelId,
        public readonly string $type = 'workflow_validation',
        public readonly string $icon = 'clipboard-document-check',
        public readonly string $color = 'amber',
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database']; // notification interne ERP : toujours

        // [CDC §Workflow — canal email optionnel] Ajouté si l'utilisateur a
        // activé la préférence sur son profil ET que le canal est permis
        // globalement (config/validation.php).
        if (config('validation.email_channel', true)
            && ($notifiable->notify_by_email ?? false)
            && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('[A3-ERP] ' . $this->title)
            ->greeting('Bonjour ' . ($notifiable->name ?? ''))
            ->line($this->message)
            ->action('Consulter le document', $this->url)
            ->line('Vous recevez cet email car les notifications par email sont activées sur votre profil.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => $this->type,
            'icon'       => $this->icon,
            'color'      => $this->color,
            'title'      => $this->title,
            'message'    => $this->message,
            'url'        => $this->url,
            'model_type' => $this->modelType,
            'model_id'   => $this->modelId,
        ];
    }

    /**
     * Envoie cette notification à tous les utilisateurs actifs portant au
     * moins un des rôles donnés — le ciblage strict par rôle qui garantit
     * que seul l'acteur attendu par le CDC est notifié.
     *
     * @param  array<int,string>  $roleNames
     */
    public static function sendToRoles(
        array $roleNames,
        string $title,
        string $message,
        string $url,
        string $modelType,
        int $modelId,
        string $type = 'workflow_validation',
        string $icon = 'clipboard-document-check',
        string $color = 'amber',
    ): void {
        // [DEFENSIVE] Spatie's role() scope throws RoleDoesNotExist if a name
        // in $roleNames isn't a registered role (e.g. a test seeds only the
        // roles it needs). Filter to existing roles first — a notification
        // that silently has zero recipients is fine; a hard crash isn't.
        $existingRoleNames = \Spatie\Permission\Models\Role::whereIn('name', $roleNames)
            ->pluck('name')->all();

        if (empty($existingRoleNames)) {
            return;
        }

        $users = User::role($existingRoleNames)->where('is_active', true)->get();

        if ($users->isEmpty()) {
            return;
        }

        $notification = new self($title, $message, $url, $modelType, $modelId, $type, $icon, $color);

        foreach ($users as $user) {
            $user->notify($notification);
        }
    }
}
