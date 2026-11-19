<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Utilisateur;

/**
 * Gestion des comptes utilisateurs et des rôles (Règle 7).
 * Réservé à l'administrateur.
 */
class UtilisateurController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin');
        $this->view('utilisateurs/liste', ['csrf' => $this->csrfField(), 'moiId' => Auth::id()]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin');
        Response::json((new Utilisateur())->paginer(
            (int)$this->input('page', 1), 20,
            (string)$this->input('q', ''),
            (string)$this->input('role', '')
        ));
    }

    public function creerForm(): void
    {
        Auth::requireRole('admin');
        $this->view('utilisateurs/form', ['csrf' => $this->csrfField(), 'utilisateur' => null]);
    }

    public function creer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();

        $email = trim((string)$this->input('email'));
        $nom = trim((string)$this->input('nom'));
        $prenom = trim((string)$this->input('prenom'));
        $telephone = trim((string)$this->input('telephone'));
        $motDePasse = (string)$this->input('mot_de_passe');
        $role = (string)$this->input('role');
        $old = ['nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'telephone' => $telephone, 'role' => $role];

        $model = new Utilisateur();
        if ($nom === '' || $prenom === '' || $email === '' || $role === '') {
            $this->flash('error', 'Merci de renseigner tous les champs obligatoires.');
            $this->rememberOldInput($old);
            Response::redirect('index.php?action=utilisateurs/creerForm');
        }
        if ($erreurMdp = Utilisateur::erreurMotDePasse($motDePasse)) {
            $this->flash('error', $erreurMdp);
            $this->rememberOldInput($old);
            Response::redirect('index.php?action=utilisateurs/creerForm');
        }
        if ($model->findByEmail($email)) {
            $this->flash('error', 'Un utilisateur avec cet email existe déjà.');
            $this->rememberOldInput($old);
            Response::redirect('index.php?action=utilisateurs/creerForm');
        }

        $id = $model->creer([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'mot_de_passe' => $motDePasse,
            'role' => $role,
            'telephone' => $telephone !== '' ? $telephone : null,
        ]);
        Historique::log('utilisateur', $id, 'creation', 'Utilisateur créé avec le rôle ' . $this->input('role') . '.');
        $this->flash('success', 'Utilisateur créé avec succès.');
        Response::redirect('index.php?action=utilisateurs');
    }

    /**
     * Permet notamment de renseigner/mettre à jour le téléphone d'un compte existant,
     * utilisé pour la vérification d'identité lors d'un mot de passe oublié.
     */
    public function editerForm(): void
    {
        Auth::requireRole('admin');
        $id = (int)$this->input('id');
        $utilisateur = (new Utilisateur())->find($id);
        if (!$utilisateur) {
            $this->flash('error', 'Utilisateur introuvable.');
            Response::redirect('index.php?action=utilisateurs');
        }
        $this->view('utilisateurs/editer', ['csrf' => $this->csrfField(), 'utilisateur' => $utilisateur]);
    }

    public function editer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Utilisateur();
        $utilisateur = $model->find($id);
        if (!$utilisateur) {
            $this->flash('error', 'Utilisateur introuvable.');
            Response::redirect('index.php?action=utilisateurs');
        }

        $nom = trim((string)$this->input('nom'));
        $prenom = trim((string)$this->input('prenom'));
        $email = trim((string)$this->input('email'));
        $telephone = trim((string)$this->input('telephone'));
        $role = (string)$this->input('role');

        if ($nom === '' || $prenom === '' || $email === '' || $role === '') {
            $this->flash('error', 'Merci de renseigner tous les champs obligatoires.');
            Response::redirect('index.php?action=utilisateurs/editerForm&id=' . $id);
        }
        $existant = $model->findByEmail($email);
        if ($existant && (int)$existant['id'] !== $id) {
            $this->flash('error', 'Un autre utilisateur utilise déjà cet email.');
            Response::redirect('index.php?action=utilisateurs/editerForm&id=' . $id);
        }

        $model->update($id, [
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $email,
            'telephone' => $telephone !== '' ? $telephone : null,
            'role' => $role,
        ]);
        Historique::log('utilisateur', $id, 'modification', 'Informations du compte mises à jour par l\'administrateur.');
        $this->flash('success', 'Utilisateur mis à jour avec succès.');
        Response::redirect('index.php?action=utilisateurs');
    }

    public function desactiver(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');

        // Un administrateur ne doit jamais pouvoir désactiver son propre compte :
        // sans quoi il se retrouverait immédiatement déconnecté (Auth::attempt()
        // refuse les comptes inactifs) et sans personne pour le réactiver.
        if ($id === Auth::id()) {
            $this->flash('error', 'Vous ne pouvez pas désactiver votre propre compte administrateur.');
            Response::redirect('index.php?action=utilisateurs');
        }

        (new Utilisateur())->update($id, ['actif' => 0]);
        Historique::log('utilisateur', $id, 'desactivation', 'Compte désactivé.');
        $this->flash('success', 'Compte désactivé.');
        Response::redirect('index.php?action=utilisateurs');
    }

    public function activer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        (new Utilisateur())->update($id, ['actif' => 1]);
        Historique::log('utilisateur', $id, 'activation', 'Compte réactivé.');
        $this->flash('success', 'Compte réactivé.');
        Response::redirect('index.php?action=utilisateurs');
    }
}
