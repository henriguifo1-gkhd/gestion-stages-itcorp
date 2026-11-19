<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Evaluation;
use App\Models\Stage;
use App\Models\PeriodeEvaluation;
use App\Models\Critere;

class EvaluationController extends Controller
{
    /**
     * Lit les notes par critère postées par le formulaire (champs note_critere_<id>).
     * Toutes les notes sont obligatoires : la note globale en est calculée par le système.
     * Retourne un message d'erreur si la grille est incomplète ou invalide, sinon null.
     */
    private function lireNotesCriteres(?array &$notes): ?string
    {
        $notes = [];
        $criteres = (new Critere())->actifs();
        if (!$criteres) {
            return 'Aucun critère d\'évaluation n\'est défini : la note globale ne peut pas être calculée.';
        }
        foreach ($criteres as $critere) {
            // Saisie au clavier : la virgule décimale (14,5) est acceptée comme le point.
            $valeur = str_replace(',', '.', trim((string)$this->input('note_critere_' . $critere['id'], '')));
            $bareme = (float)$critere['bareme'];
            if ($valeur === '' || !is_numeric($valeur)) {
                return 'Merci de noter tous les critères : la note globale est calculée automatiquement à partir de la grille.';
            }
            $note = (float)$valeur;
            if ($note < 0 || $note > $bareme) {
                return 'La note du critère « ' . $critere['libelle'] . ' » doit être comprise entre 0 et ' . $critere['bareme'] . '.';
            }
            $commentaire = trim((string)$this->input('commentaire_critere_' . $critere['id'], ''));
            $notes[] = [
                'critere_id' => (int)$critere['id'],
                'note' => $note,
                'bareme' => $bareme,
                'commentaire' => $commentaire !== '' ? $commentaire : null,
            ];
        }
        return null;
    }

    private function enregistrerCriteres(int $evaluationId, array $notes): void
    {
        $stmt = \App\Core\Database::getInstance()->prepare(
            "INSERT INTO evaluation_critere (evaluation_id, critere_id, note, commentaire) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire)"
        );
        foreach ($notes as $n) {
            $stmt->execute([$evaluationId, $n['critere_id'], $n['note'], $n['commentaire']]);
        }
    }

    /**
     * Saisie d'une évaluation par l'encadrant.
     * Fonctionnalité transverse : seul un utilisateur "encadrant" peut rédiger une évaluation.
     * Règle 5 : empêche la création de deux évaluations identiques pour la même période.
     */
    public function creer(): void
    {
        Auth::requireRole('encadrant', 'admin');
        $this->checkCsrf();

        $stageId = (int)$this->input('stage_id');
        $periodeCode = trim((string)$this->input('periode_code'));

        if (!(new PeriodeEvaluation())->existe($periodeCode)) {
            $this->flash('error', 'Période d\'évaluation invalide.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        if ($erreur = $this->lireNotesCriteres($notes)) {
            $this->flash('error', $erreur);
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        $note = Evaluation::calculerNoteGlobale($notes);

        $stageModel = new Stage();
        $stage = $stageModel->findAvecDetails($stageId);

        if (!$stage) {
            $this->flash('error', 'Stage introuvable.');
            Response::redirect('index.php?action=stages');
        }

        // Fonctionnalité transverse : seul l'encadrant affecté (ou l'admin) peut évaluer.
        if (Auth::is('encadrant') && (int)$stage['encadrant_actuel_id'] !== Auth::id()) {
            $this->flash('error', 'Seul l\'encadrant actuellement affecté à ce stage peut saisir une évaluation.');
            Response::redirect('index.php?action=stages');
        }

        // Cas limite : aucun encadrant n'est encore affecté à ce stage. Sans cela, l'évaluation
        // ne peut pas être rattachée à un encadrant (encadrant_id est obligatoire) : on bloque
        // proprement plutôt que de laisser l'insertion échouer.
        $encadrantId = Auth::is('encadrant') ? Auth::id() : $stage['encadrant_actuel_id'];
        if (!$encadrantId) {
            $this->flash('error', "Impossible de saisir une évaluation : aucun encadrant n'est actuellement affecté à ce stage. Affectez d'abord un encadrant.");
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $model = new Evaluation();
        $existante = $model->existeDeja($stageId, $periodeCode);
        if ($existante) {
            $this->flash('error', "Évaluation non enregistrée : une évaluation existe déjà pour cette période.");
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $id = $model->insert([
            'stage_id' => $stageId,
            'encadrant_id' => $encadrantId,
            'periode_code' => $periodeCode,
            'note_globale' => $note,
            'appreciation_generale' => $this->input('appreciation_generale'),
        ]);
        $this->enregistrerCriteres($id, $notes);
        Historique::log('evaluation', $id, 'creation', "Évaluation saisie pour la période {$periodeCode} (note : {$note}/20).");
        $this->flash('success', 'Évaluation enregistrée.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    /**
     * Cas limite : "évaluation déjà saisie" -> proposer la modification plutôt qu'une création en double.
     * Cas limite : "changement d'encadrant pendant une évaluation en cours" -> l'évaluation reste
     * rattachée à l'encadrant qui l'a rédigée (encadrant_id n'est jamais modifié ici).
     */
    public function modifier(): void
    {
        Auth::requireRole('encadrant', 'admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Evaluation();
        $eval = $model->find($id);
        if (!$eval) {
            Response::redirect('index.php?action=dashboard');
        }
        // Un encadrant ne modifie qu'une évaluation qu'il a rédigée ET seulement tant qu'il est
        // l'encadrant actuel du stage : un ancien encadrant n'agit plus sur le dossier.
        if (Auth::is('encadrant')) {
            $stageEval = (new Stage())->findAvecDetails((int)$eval['stage_id']);
            if ((int)$eval['encadrant_id'] !== Auth::id() || !$stageEval || (int)$stageEval['encadrant_actuel_id'] !== Auth::id()) {
                $this->flash('error', 'Vous ne pouvez modifier que les évaluations que vous avez rédigées pour un stagiaire dont vous êtes l\'encadrant.');
                Response::redirect('index.php?action=dashboard');
            }
        }
        if ($erreur = $this->lireNotesCriteres($notes)) {
            $this->flash('error', $erreur);
            Response::redirect('index.php?action=stages/voir&id=' . $eval['stage_id']);
        }
        $note = Evaluation::calculerNoteGlobale($notes);
        $model->update($id, [
            'note_globale' => $note,
            'appreciation_generale' => $this->input('appreciation_generale'),
        ]);
        $this->enregistrerCriteres($id, $notes);
        Historique::log('evaluation', $id, 'modification', 'Évaluation modifiée (période ' . $eval['periode_code'] . ', note recalculée : ' . $note . '/20).');
        $this->flash('success', 'Évaluation mise à jour.');
        Response::redirect('index.php?action=stages/voir&id=' . $eval['stage_id']);
    }
}
