<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Notification;

/**
 * Notifications internes de l'administrateur et de l'encadrant (dépôts de livrables).
 * Chaque utilisateur ne voit et ne modifie que SES notifications.
 */
class NotificationController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->view('notifications/liste', ['csrf' => $this->csrfField()]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'encadrant');
        Response::json((new Notification())->paginerPourUtilisateur((int)Auth::id(), (int)$this->input('page', 1), 20));
    }

    /** Ouvre une notification : la marque comme lue puis redirige vers la page concernée. */
    public function ouvrir(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $id = (int)$this->input('id');
        $model = new Notification();
        $notification = $model->find($id);
        if (!$notification || (int)$notification['utilisateur_id'] !== Auth::id()) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        $model->marquerLue($id, (int)Auth::id());
        // Le lien est écrit par le serveur ; on ne redirige tout de même que vers une page interne.
        $lien = (string)($notification['lien'] ?? '');
        Response::redirect(str_starts_with($lien, 'index.php?action=') ? $lien : 'index.php?action=notifications');
    }

    public function toutLu(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->checkCsrf();
        (new Notification())->toutMarquerLu((int)Auth::id());
        $this->flash('success', 'Toutes les notifications ont été marquées comme lues.');
        Response::redirect('index.php?action=notifications');
    }
}
