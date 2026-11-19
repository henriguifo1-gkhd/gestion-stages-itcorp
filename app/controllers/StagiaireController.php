<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Stagiaire;
use App\Models\Convention;
use App\Models\Stage;

class StagiaireController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $this->view('stagiaires/liste', [
            'csrf' => $this->csrfField(),
            'etablissements' => (new Stagiaire())->etablissements(),
        ]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $model = new Stagiaire();
        Response::json($model->paginer(
            (int)$this->input('page', 1), 20,
            (string)$this->input('q', ''),
            (string)$this->input('statut', ''),
            (string)$this->input('etablissement', '')
        ));
    }

    public function voir(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction', 'stagiaire');
        $id = (int)$this->input('id');
        $stagiaire = (new Stagiaire())->findAvecDetails($id);
        if (!$stagiaire) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }
        // Un stagiaire ne peut consulter que son propre dossier (Règle 7 : droits d'accès par rôle).
        if (Auth::is('stagiaire') && (int)($stagiaire['utilisateur_id'] ?? 0) !== Auth::id()) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        $conventions = (new Convention())->parStagiaire($id);
        $stmt = \App\Core\Database::getInstance()->prepare(
            "SELECT stg.*,
                    (SELECT aff.encadrant_id FROM affectation aff
                      WHERE aff.stage_id = stg.id AND aff.date_fin IS NULL) AS encadrant_actuel_id
             FROM stage stg JOIN convention c ON c.id = stg.convention_id
             WHERE c.stagiaire_id = ? ORDER BY stg.id DESC"
        );
        $stmt->execute([$id]);
        $stages = $stmt->fetchAll();
        // Un stagiaire ne peut avoir qu'un seul stage actif (a_venir/en_cours) à la fois.
        $aUnStageActif = (bool)array_filter($stages, fn($s) => in_array($s['statut'], ['a_venir', 'en_cours'], true));

        $this->view('stagiaires/voir', [
            'stagiaire' => $stagiaire,
            'conventions' => $conventions,
            'stages' => $stages,
            'aUnStageActif' => $aUnStageActif,
            'historique' => Historique::pour('stagiaire', $id),
            'comptesDisponibles' => Auth::is('admin') ? (new \App\Models\Utilisateur())->stagiairesNonLies() : [],
            'csrf' => $this->csrfField(),
        ]);
    }

    /** Lie la fiche stagiaire à un compte utilisateur (rôle stagiaire) pour lui donner accès à son espace personnel. */
    public function lierCompte(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $utilisateurId = (int)$this->input('utilisateur_id');

        $utilisateurModel = new \App\Models\Utilisateur();
        $compte = $utilisateurModel->find($utilisateurId);
        if (!$compte || $compte['role'] !== 'stagiaire') {
            $this->flash('error', 'Seul un compte de rôle "stagiaire" peut être lié à une fiche stagiaire.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $id);
        }

        (new Stagiaire())->lierCompte($id, $utilisateurId);
        Historique::log('stagiaire', $id, 'liaison_compte', 'Fiche liée au compte utilisateur ' . $compte['email'] . ', pour l\'accès à l\'espace personnel du stagiaire.');
        $this->flash('success', 'Compte utilisateur lié avec succès.');
        Response::redirect('index.php?action=stagiaires/voir&id=' . $id);
    }

    public function delierCompte(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        (new Stagiaire())->delierCompte($id);
        Historique::log('stagiaire', $id, 'delaison_compte', 'Fiche déliée de son compte utilisateur.');
        $this->flash('success', 'Compte utilisateur délié.');
        Response::redirect('index.php?action=stagiaires/voir&id=' . $id);
    }

    public function editerForm(): void
    {
        Auth::requireRole('admin');
        $id = (int)$this->input('id');
        $stagiaire = (new Stagiaire())->find($id);
        $this->view('stagiaires/form', ['stagiaire' => $stagiaire, 'csrf' => $this->csrfField()]);
    }

    public function editer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Stagiaire();
        $model->update($id, [
            'sexe' => $this->input('sexe') === 'F' ? 'F' : 'M',
            'telephone' => $this->input('telephone'),
            'filiere' => $this->input('filiere'),
            'niveau_etude' => $this->input('niveau_etude'),
            'etablissement' => $this->input('etablissement'),
        ]);
        Historique::log('stagiaire', $id, 'modification', 'Informations personnelles mises à jour.');
        $this->flash('success', 'Fiche stagiaire mise à jour.');
        Response::redirect('index.php?action=stagiaires/voir&id=' . $id);
    }

    /** Règle 8 / cas limite : suppression interdite si historique -> archivage à la place. */
    public function supprimer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Stagiaire();

        // Un stagiaire encore titulaire d'un stage actif (à venir ou en cours) ne peut pas être
        // archivé : il faut d'abord clôturer ou interrompre ce stage, sinon on archiverait
        // quelqu'un qui est censé être toujours en activité chez nous.
        if ((new \App\Models\Stage())->aUnStageActif($id)) {
            $this->flash('error', 'Impossible d\'archiver ou de supprimer ce stagiaire : il a encore un stage en cours ou à venir. Clôturez ou interrompez-le d\'abord.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $id);
        }

        if ($model->possedeHistorique($id)) {
            $model->archiver($id);
            Historique::log('stagiaire', $id, 'archivage', 'Suppression refusée (historique existant) : dossier archivé.');
            $this->flash('warning', 'Stagiaire archivé.');
        } else {
            $stagiaire = $model->find($id);
            if (!$stagiaire) {
                Response::redirect('index.php?action=stagiaires');
            }
            $model->supprimerSansHistorique($id);
            Historique::log('stagiaire', $id, 'suppression', 'Fiche de ' . $stagiaire['nom'] . ' ' . $stagiaire['prenom']
                . ' (' . $stagiaire['email'] . ') supprimée : aucun historique. Candidature(s) d\'origine conservée(s).');
            $this->flash('success', 'Stagiaire supprimé.');
        }
        Response::redirect('index.php?action=stagiaires');
    }
}
