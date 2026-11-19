<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Mailer;
use App\Core\Response;
use App\Models\Candidature;
use App\Models\Stagiaire;

/**
 * Gestion des candidatures.
 * Droits : admin/responsable des stages uniquement (Fonctionnalité transverse Règle 7).
 */
class CandidatureController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin', 'direction');
        $this->view('candidatures/liste', ['csrf' => $this->csrfField()]);
    }

    /** Endpoint AJAX paginé (20 lignes max). */
    public function apiListe(): void
    {
        Auth::requireRole('admin', 'direction');
        $page = (int)$this->input('page', 1);
        $q = (string)$this->input('q', '');
        $statut = (string)$this->input('statut', '');
        $model = new Candidature();
        Response::json($model->paginer($page, 20, $q, $statut));
    }

    public function creerForm(): void
    {
        Auth::requireRole('admin');
        $this->view('candidatures/form', ['csrf' => $this->csrfField(), 'candidature' => null]);
    }

    public function creer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();

        $sexe = $this->input('sexe') === 'F' ? 'F' : 'M';
        $data = [
            'nom_complet' => trim((string)$this->input('nom_complet')),
            'sexe' => $sexe,
            'email' => trim((string)$this->input('email')),
            'telephone' => trim((string)$this->input('telephone')),
            'etablissement' => trim((string)$this->input('etablissement')),
            'filiere' => trim((string)$this->input('filiere')),
            'niveau_etude' => trim((string)$this->input('niveau_etude')),
            'poste' => trim((string)$this->input('poste')),
            'date_candidature' => $this->input('date_candidature', date('Y-m-d')),
            'statut' => 'en_attente',
        ];

        if ($data['nom_complet'] === '' || $data['email'] === '' || $data['etablissement'] === '' || $data['poste'] === '') {
            $this->flash('error', 'Merci de renseigner au minimum le nom, l\'email, l\'établissement et le poste.');
            $this->rememberOldInput($data);
            Response::redirect('index.php?action=candidatures/creerForm');
        }

        $model = new Candidature();

        // Cas limite : candidature en double pour le même poste -> on avertit mais on enregistre quand même,
        // conformément au cahier des charges ("signale le doublon plutôt que de créer deux dossiers distincts").
        $doublon = $model->detecterDoublon($data['email'], $data['poste'], $data['etablissement']);

        $id = $model->insert($data);
        Historique::log('candidature', $id, 'creation', 'Candidature créée pour le poste ' . $data['poste']);

        if ($doublon) {
            $this->flash('warning', "Candidature enregistrée (#{$id}) - attention, un doublon potentiel existe déjà (dossier #{$doublon['id']}).");
        } else {
            $this->flash('success', 'Candidature enregistrée avec succès.');
        }
        Response::redirect('index.php?action=candidatures');
    }

    /**
     * Acceptation : Règle 1 (respect de l'ordre des étapes) + Règle 2 (réutilisation des infos).
     * Crée automatiquement la fiche stagiaire à partir de la candidature.
     */
    public function accepter(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');

        $candModel = new Candidature();
        $candidature = $candModel->find($id);
        if (!$candidature) {
            $this->flash('error', 'Candidature introuvable.');
            Response::redirect('index.php?action=candidatures');
        }
        if ($candidature['statut'] !== 'en_attente') {
            $this->flash('error', 'Seule une candidature "en attente" peut être acceptée.');
            Response::redirect('index.php?action=candidatures');
        }

        $candModel->update($id, ['statut' => 'acceptee']);

        // Règle 2 : réutilisation des informations déjà saisies, aucune ressaisie.
        // Une même personne (identifiée par son email, unique en base) ne doit avoir
        // qu'une seule fiche stagiaire : si elle a déjà été stagiaire par le passé
        // (nouvelle candidature après un stage précédent), on réutilise sa fiche
        // existante plutôt que d'en recréer une (qui violerait l'unicité de l'email).
        $stagiaireModel = new Stagiaire();
        $stagiaireExistant = $stagiaireModel->findByEmail($candidature['email']);

        if ($stagiaireExistant) {
            $stagiaireId = (int)$stagiaireExistant['id'];
            Historique::log('candidature', $id, 'acceptation', 'Candidature acceptée -> fiche stagiaire existante #' . $stagiaireId . ' réutilisée');
            // Une fiche désistée ou archivée lors d'un précédent parcours redevient active :
            // sans cela, aucune convention ne pourrait lui être rattachée.
            // Le sexe saisi sur la nouvelle candidature fait foi pour les documents générés
            // ensuite (convention, attestation...) : on le reporte sur la fiche réutilisée.
            $sexeCandidature = ($candidature['sexe'] ?? 'M') === 'F' ? 'F' : 'M';
            if (($stagiaireExistant['sexe'] ?? 'M') !== $sexeCandidature) {
                $stagiaireModel->update($stagiaireId, ['sexe' => $sexeCandidature]);
            }
            if ($stagiaireExistant['statut'] !== 'actif') {
                $stagiaireModel->update($stagiaireId, ['statut' => 'actif']);
                Historique::log('stagiaire', $stagiaireId, 'reactivation',
                    'Fiche réactivée (statut précédent : ' . $stagiaireExistant['statut'] . ') suite à l\'acceptation de la candidature #' . $id . '.');
            }
        } else {
            $stagiaireId = $stagiaireModel->insert([
                'nom' => explode(' ', $candidature['nom_complet'])[0],
                'prenom' => trim(str_replace(explode(' ', $candidature['nom_complet'])[0], '', $candidature['nom_complet'])) ?: $candidature['nom_complet'],
                'sexe' => $candidature['sexe'] ?? 'M',
                'email' => $candidature['email'],
                'telephone' => $candidature['telephone'],
                'etablissement' => $candidature['etablissement'],
                'filiere' => $candidature['filiere'],
                'niveau_etude' => $candidature['niveau_etude'],
                'statut' => 'actif',
            ]);
            Historique::log('candidature', $id, 'acceptation', 'Candidature acceptée -> fiche stagiaire #' . $stagiaireId . ' créée');
            Historique::log('stagiaire', $stagiaireId, 'creation', 'Créé à partir de la candidature #' . $id);
        }

        // Le lien candidature -> stagiaire ne peut être posé qu'une fois la candidature
        // acceptée (contrainte chk_candidature_lien), ce qui est désormais le cas.
        $candModel->update($id, ['stagiaire_id' => $stagiaireId]);

        $this->flash('success', 'Candidature acceptée. La fiche stagiaire a été ' . ($stagiaireExistant ? 'réutilisée' : 'créée automatiquement') . '. Vous pouvez maintenant informer le candidat par mail.');
        Response::redirect('index.php?action=candidatures/mailForm&id=' . $id);
    }

    public function refuser(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Candidature();
        $c = $model->find($id);
        if ($c && $c['statut'] === 'en_attente') {
            $model->update($id, ['statut' => 'refusee']);
            Historique::log('candidature', $id, 'refus', 'Candidature refusée');
            $this->flash('success', 'Candidature refusée. Vous pouvez maintenant informer le candidat par mail.');
            Response::redirect('index.php?action=candidatures/mailForm&id=' . $id);
        }
        Response::redirect('index.php?action=candidatures');
    }

    /** Formulaire d'envoi d'un mail au candidat, pré-rempli selon la décision (texte modifiable). */
    public function mailForm(): void
    {
        Auth::requireRole('admin');
        $model = new Candidature();
        $candidature = $model->find((int)$this->input('id'));
        if (!$candidature) {
            $this->flash('error', 'Candidature introuvable.');
            Response::redirect('index.php?action=candidatures');
        }
        $config = require __DIR__ . '/../../config/config.php';
        $this->view('candidatures/mail', [
            'csrf' => $this->csrfField(),
            'candidature' => $candidature,
            'modeleMail' => $model->modeleMail($candidature, $this->input('modele'), $config['mail']['nom_expediteur']),
        ]);
    }

    public function envoyerMail(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Candidature();
        $candidature = $model->find($id);
        if (!$candidature) {
            $this->flash('error', 'Candidature introuvable.');
            Response::redirect('index.php?action=candidatures');
        }

        $sujet = trim((string)$this->input('sujet'));
        $corps = trim((string)$this->input('corps'));
        $retour = 'index.php?action=candidatures/mailForm&id=' . $id;
        if ($sujet === '' || $corps === '') {
            $this->flash('error', 'Merci de renseigner l\'objet et le message.');
            $this->rememberOldInput(['sujet' => $sujet, 'corps' => $corps]);
            Response::redirect($retour);
        }

        $mailer = new Mailer();
        if (!$mailer->envoyer($candidature['email'], $candidature['nom_complet'], $sujet, $corps)) {
            $this->flash('error', 'Le mail n\'a pas pu être envoyé : ' . $mailer->erreur());
            $this->rememberOldInput(['sujet' => $sujet, 'corps' => $corps]);
            Response::redirect($retour);
        }

        $model->update($id, ['dernier_mail_le' => date('Y-m-d H:i:s')]);
        $journal = $mailer->mode() === 'journal';
        Historique::log('candidature', $id, 'mail_candidat',
            'Mail « ' . $sujet . ' » ' . ($journal ? 'enregistré (mode journal, non envoyé)' : 'envoyé') . ' à ' . $candidature['email']
            . ($mailer->dernierAccuse() !== '' ? ' [accusé serveur : ' . mb_substr($mailer->dernierAccuse(), 0, 120) . ']' : ''));

        if ($journal) {
            $this->flash('warning', 'ATTENTION : le mail n\'a PAS été envoyé à ' . $candidature['email']
                . ' : l\'envoi réel n\'est pas configuré (il a seulement été enregistré dans storage/mails). '
                . 'Renseignez le compte d\'envoi dans config/mail.local.php.');
        } else {
            $this->flash('success', 'Mail envoyé à ' . $candidature['email'] . '.');
        }
        Response::redirect('index.php?action=candidatures');
    }

    /** Cas limite : désistement après acceptation, avant signature de convention (Règle 6). */
    public function desister(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Candidature();
        $c = $model->find($id);
        if (!$c) {
            Response::redirect('index.php?action=candidatures');
        }
        if (!in_array($c['statut'], ['en_attente', 'acceptee'], true)) {
            $this->flash('error', 'Seule une candidature en attente ou acceptée peut faire l\'objet d\'un désistement.');
            Response::redirect('index.php?action=candidatures');
        }
        // Le désistement intervient avant le démarrage : un stagiaire déjà en stage doit voir
        // son stage interrompu (détail du stage), pas sa candidature marquée désistée.
        if (!empty($c['stagiaire_id']) && (new \App\Models\Stage())->aUnStageActif((int)$c['stagiaire_id'])) {
            $this->flash('error', 'Ce stagiaire a un stage en cours ou à venir : interrompez ce stage depuis son détail plutôt que d\'enregistrer un désistement.');
            Response::redirect('index.php?action=candidatures');
        }
        $model->update($id, ['statut' => 'desistee']);
        $g = \App\Core\Genre::mots($c['sexe'] ?? 'M');
        Historique::log('candidature', $id, 'desistement', $g['Le_candidat'] . ' s\'est ' . $g['desiste'] . ' après acceptation. Dossier conservé (non supprimé).');

        // Si une fiche stagiaire existe déjà (candidature préalablement acceptée et
        // liée via candidature.stagiaire_id), on la marque désistée aussi (traçabilité).
        if (!empty($c['stagiaire_id'])) {
            $stagiaireModel = new Stagiaire();
            $stagiaireModel->marquerDesiste((int)$c['stagiaire_id']);
            Historique::log('stagiaire', (int)$c['stagiaire_id'], 'desistement', 'Désistement du stagiaire, historique conservé.');
        }

        $this->flash('success', 'Désistement enregistré. Le dossier est conservé pour traçabilité.');
        Response::redirect('index.php?action=candidatures');
    }
}
