<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Utilisateur;

/**
 * Auto-service du compte utilisateur (accessible à tous les rôles connectés) :
 * modification du mot de passe.
 */
class MonCompteController extends Controller
{
    public function motDePasseForm(): void
    {
        Auth::requireLogin();
        $this->view('mon_compte/mot_de_passe', ['csrf' => $this->csrfField()]);
    }

    public function motDePasse(): void
    {
        Auth::requireLogin();
        $this->checkCsrf();

        $ancien = (string)$this->input('ancien_mot_de_passe');
        $nouveau = (string)$this->input('nouveau_mot_de_passe');
        $confirmation = (string)$this->input('confirmation_mot_de_passe');

        $model = new Utilisateur();
        $user = $model->find((int)Auth::id());

        if (!$user || !password_verify($ancien, $user['mot_de_passe'])) {
            $this->flash('error', 'L\'ancien mot de passe est incorrect.');
            Response::redirect('index.php?action=mon-compte/motDePasseForm');
        }
        if ($erreurMdp = Utilisateur::erreurMotDePasse($nouveau)) {
            $this->flash('error', $erreurMdp);
            Response::redirect('index.php?action=mon-compte/motDePasseForm');
        }
        if ($nouveau !== $confirmation) {
            $this->flash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
            Response::redirect('index.php?action=mon-compte/motDePasseForm');
        }
        if (password_verify($nouveau, $user['mot_de_passe'])) {
            $this->flash('error', 'Le nouveau mot de passe doit être différent de l\'ancien mot de passe.');
            Response::redirect('index.php?action=mon-compte/motDePasseForm');
        }

        $model->update((int)Auth::id(), ['mot_de_passe' => password_hash($nouveau, PASSWORD_BCRYPT)]);
        Historique::log('utilisateur', (int)Auth::id(), 'modification', 'Mot de passe modifié par l\'utilisateur lui-même.');
        $this->flash('success', 'Votre mot de passe a été modifié avec succès.');
        Response::redirect('index.php?action=dashboard');
    }
}
