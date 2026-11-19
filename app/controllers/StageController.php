<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Stage;
use App\Models\Convention;
use App\Models\Affectation;
use App\Models\Utilisateur;
use App\Models\Evaluation;
use App\Models\Stagiaire;
use App\Models\PeriodeEvaluation;
use App\Models\Critere;
use App\Models\Notification;

class StageController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $this->view('stages/liste', [
            'csrf' => $this->csrfField(),
            'encadrants' => (new Utilisateur())->encadrants(),
        ]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $model = new Stage();
        $encadrantId = null;
        // Un encadrant ne voit par défaut que ses stages (fonctionnalité transverse) sauf filtre explicite admin.
        if (Auth::is('encadrant')) {
            $encadrantId = Auth::id();
        } elseif ($this->input('encadrant_id')) {
            $encadrantId = (int)$this->input('encadrant_id');
        }
        Response::json($model->paginer(
            (int)$this->input('page', 1), 20,
            (string)$this->input('q', ''),
            (string)$this->input('statut', ''),
            (string)$this->input('etablissement', ''),
            $encadrantId
        ));
    }

    public function creerForm(): void
    {
        Auth::requireRole('admin');
        $id = (int)$this->input('convention_id');
        $convention = (new Convention())->find($id);
        $stagiaireADejaUnStageActif = $convention
            ? (new Stage())->aUnStageActif((int)$convention['stagiaire_id'])
            : false;
        $this->view('stages/form', [
            'convention' => $convention,
            'stagiaireADejaUnStageActif' => $stagiaireADejaUnStageActif,
            'csrf' => $this->csrfField(),
        ]);
    }

    /** Règle 3 : un stage doit être associé à une convention valide (statut = validee). */
    public function creer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();

        $conventionId = (int)$this->input('convention_id');
        $conventionModel = new Convention();
        $convention = $conventionModel->find($conventionId);

        if (!$convention) {
            $this->flash('error', 'Convention introuvable.');
            Response::redirect('index.php?action=stagiaires');
        }
        if (!$conventionModel->estValide($convention)) {
            $this->flash('error', 'Impossible de créer le stage : la convention n\'est pas validée.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }
        if ((new Stage())->aUnStageActif((int)$convention['stagiaire_id'])) {
            $this->flash('error', 'Impossible de créer le stage : ce stagiaire a déjà un stage en cours ou à venir.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }

        $dateDebut = trim((string)$this->input('date_debut'));
        $dateFin = trim((string)$this->input('date_fin'));
        $retourForm = 'index.php?action=stages/creerForm&convention_id=' . $conventionId;
        if ($dateDebut === '' || $dateFin === '' || !strtotime($dateDebut) || !strtotime($dateFin)) {
            $this->flash('error', 'Merci de renseigner des dates de début et de fin valides.');
            Response::redirect($retourForm);
        }
        if (strtotime($dateFin) < strtotime($dateDebut)) {
            $this->flash('error', 'La date de fin du stage ne peut pas précéder sa date de début.');
            Response::redirect($retourForm);
        }
        // Règle appliquée aussi par la base (trigger) : le stage reste dans la période de la convention.
        if (strtotime($dateDebut) < strtotime($convention['date_debut']) || strtotime($dateFin) > strtotime($convention['date_fin'])) {
            $this->flash('error', 'Les dates du stage doivent être comprises dans la période de la convention (du '
                . $convention['date_debut'] . ' au ' . $convention['date_fin'] . '). Prolongez la convention si nécessaire.');
            Response::redirect($retourForm);
        }

        $stageId = (new Stage())->insert([
            'convention_id' => $conventionId,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'statut' => 'a_venir',
        ]);
        Historique::log('stage', $stageId, 'creation', 'Stage créé (en attente d\'affectation d\'un encadrant).');

        $this->flash('success', 'Stage créé.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    public function voir(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction', 'stagiaire');
        $id = (int)$this->input('id');
        $stage = (new Stage())->findAvecDetails($id);
        if (!$stage) {
            http_response_code(404);
            require __DIR__ . '/../views/errors/404.php';
            return;
        }
        // Un stagiaire ne peut consulter que ses propres stages (Règle 7).
        if (Auth::is('stagiaire')) {
            $monDossier = (new Stagiaire())->findByUtilisateurId((int)Auth::id());
            if (!$monDossier || (int)$monDossier['id'] !== (int)$stage['stagiaire_id']) {
                http_response_code(403);
                require __DIR__ . '/../views/errors/403.php';
                return;
            }
        }
        // Un encadrant n'accède qu'aux stages dont il est l'encadrant actuel : il ne voit ni les
        // livrables, ni les évaluations, ni les informations des stagiaires des autres encadrants.
        if (Auth::is('encadrant') && (int)$stage['encadrant_actuel_id'] !== Auth::id()) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }

        // Un stagiaire ne voit ses évaluations qu'une fois son stage terminé : tant que le stage
        // n'est pas clôturé, elles ne sont même pas lues en base pour cette requête.
        $evaluationsVisibles = !Auth::is('stagiaire') || $stage['statut'] === 'termine';
        $evaluations = [];
        if ($evaluationsVisibles) {
            $evaluationModel = new Evaluation();
            $evaluations = $evaluationModel->toutesParStage($id);
            $periodesParCode = [];
            foreach ((new PeriodeEvaluation())->toutes() as $p) {
                $periodesParCode[$p['code']] = $p['libelle'];
            }
            foreach ($evaluations as &$evaluation) {
                $evaluation['periode_libelle'] = $periodesParCode[$evaluation['periode_code']] ?? $evaluation['periode_code'];
                $evaluation['criteres'] = $evaluationModel->criteres((int)$evaluation['id']);
            }
            unset($evaluation);
        }
        // Actions d'écriture sur le stage (échéances, évaluations) : admin, ou encadrant actuel.
        $peutGerer = Auth::is('admin') || (Auth::is('encadrant') && (int)$stage['encadrant_actuel_id'] === Auth::id());

        $this->view('stages/voir', [
            'stage' => $stage,
            'affectations' => (new Affectation())->historiquePourStage($id),
            'encadrants' => (new Utilisateur())->encadrants(),
            'evaluations' => $evaluations,
            'evaluationsVisibles' => $evaluationsVisibles,
            'peutGerer' => $peutGerer,
            'periodesEvaluation' => (new PeriodeEvaluation())->toutes(),
            'criteresEvaluation' => (new Critere())->actifs(),
            'incoherenceConvention' => (new Stage())->conventionEnIncoherence($stage),
            'attestation' => (new \App\Models\Attestation())->parStage($id),
            'historique' => Historique::pour('stage', $id),
            'csrf' => $this->csrfField(),
        ]);
    }

    /**
     * Affectation d'un encadrant (Règle 4 + Fonctionnalité transverse) :
     * seuls les utilisateurs de rôle "encadrant" peuvent être affectés.
     * Contrôle appliqué au niveau applicatif (pas de contrainte structurelle en base, conformément au cahier des charges).
     */
    public function affecter(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $stageId = (int)$this->input('stage_id');
        $encadrantId = (int)$this->input('encadrant_id');

        $utilisateurModel = new Utilisateur();
        $encadrant = $utilisateurModel->find($encadrantId);
        if (!$encadrant || $encadrant['role'] !== 'encadrant') {
            $this->flash('error', 'Seul un utilisateur ayant le rôle "encadrant" peut être affecté à un stage.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $stage = (new Stage())->findAvecDetails($stageId);
        if (!$stage) {
            $this->flash('error', 'Stage introuvable.');
            Response::redirect('index.php?action=stages');
        }
        $dejaEncadrant = (int)$stage['encadrant_actuel_id'] === $encadrantId;

        $affectationModel = new Affectation();
        $affectationModel->reaffecter($stageId, $encadrantId);

        // L'encadrant est notifié qu'un stagiaire lui est confié (pas de doublon s'il l'encadrait déjà).
        if (!$dejaEncadrant) {
            (new Notification())->notifierAffectation($encadrantId, $stageId,
                trim($stage['stagiaire_nom'] . ' ' . $stage['stagiaire_prenom']), (string)$stage['etablissement'],
                (string)($stage['stagiaire_sexe'] ?? 'M'));
        }

        // L'affectation (table affectation) est désormais l'unique source de vérité
        // pour l'encadrant courant d'un stage : la colonne stage.encadrant_actuel_id
        // n'existe plus dans ce schéma.
        Historique::log('stage', $stageId, 'affectation_encadrant', 'Encadrant affecté : ' . $encadrant['prenom'] . ' ' . $encadrant['nom']);

        $this->flash('success', 'Encadrant affecté avec succès' . ($dejaEncadrant ? '' : ' et notifié') . '.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    /** Cas limite : un stage ne peut passer "en cours" sans encadrant affecté. */
    public function demarrer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $stageId = (int)$this->input('id');
        $model = new Stage();
        $stage = $model->findAvecDetails($stageId);

        if (!$stage) {
            Response::redirect('index.php?action=stages');
        }
        if ($stage['statut'] !== 'a_venir') {
            $this->flash('error', 'Seul un stage « à venir » peut être démarré.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        if (!$stage['encadrant_actuel_id']) {
            $this->flash('error', 'Impossible de démarrer le stage : aucun encadrant n\'est affecté.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        $model->update($stageId, ['statut' => 'en_cours']);
        Historique::log('stage', $stageId, 'demarrage', 'Stage passé en statut "en_cours".');
        $this->flash('success', 'Stage démarré.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    /** Clôture du stage -> Règle 1 (ordre des étapes) : évaluation avant attestation. */
    public function cloturer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $stageId = (int)$this->input('id');
        $model = new Stage();
        $stage = $model->find($stageId);
        if (!$stage) {
            Response::redirect('index.php?action=stages');
        }
        if ($stage['statut'] !== 'en_cours') {
            $this->flash('error', 'Seul un stage « en cours » peut être clôturé.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        $model->update($stageId, ['statut' => 'termine']);
        Historique::log('stage', $stageId, 'cloture', 'Stage clôturé (terminé).');
        $this->flash('success', 'Stage clôturé.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    /** Cas limite : stage interrompu avant son terme (abandon, rupture de convention). */
    public function interrompre(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $stageId = (int)$this->input('id');
        $motif = trim((string)$this->input('motif', ''));
        $model = new Stage();
        $stage = $model->find($stageId);
        if (!$stage) {
            Response::redirect('index.php?action=stages');
        }
        // Un stage terminé (a fortiori attesté) ou déjà interrompu ne peut plus être interrompu.
        if (!in_array($stage['statut'], ['a_venir', 'en_cours'], true)) {
            $this->flash('error', 'Seul un stage « à venir » ou « en cours » peut être interrompu.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        $model->update($stageId, ['statut' => 'interrompu']);
        Historique::log('stage', $stageId, 'interruption', 'Stage interrompu avant son terme. Motif : ' . $motif);
        $this->flash('warning', 'Stage interrompu.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }
}
