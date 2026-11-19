<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Utilisateur;

/**
 * Mot de passe oublié.
 * L'application tournant en local (pas de serveur d'envoi d'email ni de SMS),
 * la récupération ne peut pas passer par un lien/code envoyé par email ou SMS :
 * l'identité est donc vérifiée par correspondance email + numéro de téléphone
 * (les deux doivent correspondre à un compte actif), après quoi l'utilisateur
 * peut directement définir un nouveau mot de passe.
 */
class MotDePasseOublieController extends Controller
{
    private const DUREE_VALIDITE = 600; // 10 minutes

    public function form(): void
    {
        if (Auth::check()) {
            Response::redirect('index.php?action=dashboard');
        }
        $this->viewPlain('mot_de_passe_oublie/verifier', ['csrf' => $this->csrfField()]);
    }

    public function verifier(): void
    {
        $this->checkCsrf();
        $email = trim((string)$this->input('email'));
        $telephone = trim((string)$this->input('telephone'));

        if ($email === '' || $telephone === '') {
            $this->flash('error', 'Merci de renseigner votre email et votre numéro de téléphone.');
            Response::redirect('index.php?action=mot-de-passe-oublie');
        }

        $model = new Utilisateur();
        $utilisateur = $model->findByEmailEtTelephone($email, $telephone);
        if (!$utilisateur) {
            $this->flash('error', "Aucun compte actif ne correspond à cet email et ce numéro de téléphone. Vérifiez vos informations ou contactez l'administrateur.");
            Response::redirect('index.php?action=mot-de-passe-oublie');
        }

        Auth::start();
        $_SESSION['reset_utilisateur_id'] = $utilisateur['id'];
        $_SESSION['reset_expire'] = time() + self::DUREE_VALIDITE;
        Response::redirect('index.php?action=mot-de-passe-oublie/reinitialiserForm');
    }

    private function utilisateurVerifie(): ?array
    {
        Auth::start();
        $id = $_SESSION['reset_utilisateur_id'] ?? null;
        $expire = $_SESSION['reset_expire'] ?? 0;
        if (!$id || time() > $expire) {
            unset($_SESSION['reset_utilisateur_id'], $_SESSION['reset_expire']);
            return null;
        }
        return (new Utilisateur())->find((int)$id);
    }

    public function reinitialiserForm(): void
    {
        $utilisateur = $this->utilisateurVerifie();
        if (!$utilisateur) {
            $this->flash('error', 'Votre session de vérification a expiré. Merci de recommencer.');
            Response::redirect('index.php?action=mot-de-passe-oublie');
        }
        $this->viewPlain('mot_de_passe_oublie/reinitialiser', [
            'csrf' => $this->csrfField(),
            'utilisateur' => $utilisateur,
        ]);
    }

    public function reinitialiser(): void
    {
        $this->checkCsrf();
        $utilisateur = $this->utilisateurVerifie();
        if (!$utilisateur) {
            $this->flash('error', 'Votre session de vérification a expiré. Merci de recommencer.');
            Response::redirect('index.php?action=mot-de-passe-oublie');
        }

        $nouveau = (string)$this->input('nouveau_mot_de_passe');
        $confirmation = (string)$this->input('confirmation_mot_de_passe');

        if ($erreurMdp = Utilisateur::erreurMotDePasse($nouveau)) {
            $this->flash('error', $erreurMdp);
            Response::redirect('index.php?action=mot-de-passe-oublie/reinitialiserForm');
        }
        if ($nouveau !== $confirmation) {
            $this->flash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
            Response::redirect('index.php?action=mot-de-passe-oublie/reinitialiserForm');
        }

        $model = new Utilisateur();
        $model->update((int)$utilisateur['id'], ['mot_de_passe' => password_hash($nouveau, PASSWORD_BCRYPT)]);
        Historique::log('utilisateur', (int)$utilisateur['id'], 'reinitialisation_mot_de_passe',
            'Mot de passe réinitialisé via « Mot de passe oublié » (vérifié par email + téléphone).');

        Auth::start();
        unset($_SESSION['reset_utilisateur_id'], $_SESSION['reset_expire']);

        $this->flash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez vous connecter.');
        Response::redirect('index.php?action=login');
    }
}
