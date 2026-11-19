<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Models\Stage;
use App\Models\Stagiaire;
use App\Models\Candidature;
use App\Models\Livrable;

/**
 * Tableau de bord : partagé par admin et encadrant (cf. cahier des charges).
 * Consultation infos de session, suivi des stages, recherche/filtres, stats.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        Auth::requireLogin();

        // Le stagiaire a un espace personnel dédié : il consulte son propre dossier,
        // pas les statistiques globales ni la recherche parmi tous les stagiaires
        // (ces données sont réservées à admin/encadrant/direction - Règle 7).
        if (Auth::is('stagiaire')) {
            $stagiaireModel = new Stagiaire();
            $monDossier = $stagiaireModel->findByUtilisateurId((int)Auth::id());
            $mesStages = [];
            if ($monDossier) {
                $stmt = \App\Core\Database::getInstance()->prepare(
                    "SELECT stg.* FROM stage stg JOIN convention c ON c.id = stg.convention_id
                     WHERE c.stagiaire_id = ? ORDER BY stg.id DESC"
                );
                $stmt->execute([$monDossier['id']]);
                $mesStages = $stmt->fetchAll();
            }
            $this->view('dashboard/index', [
                'monDossier' => $monDossier,
                'mesStages' => $mesStages,
                'csrf' => $this->csrfField(),
            ]);
            return;
        }

        $stageModel = new Stage();
        $stats = $stageModel->compterParStatut();

        $db = (new Stagiaire())->all();
        $totalStagiaires = count($db);

        $candModel = new Candidature();
        $candidaturesEnAttente = 0;
        foreach ($candModel->all() as $c) {
            if ($c['statut'] === 'en_attente') $candidaturesEnAttente++;
        }

        // Livrables déposés en attente de validation : un encadrant ne compte que ceux de ses stagiaires.
        $livrModel = new Livrable();
        $encadrantId = Auth::is('encadrant') ? Auth::id() : null;
        $livrablesEnAttente = $livrModel->compterDeposesEnAttente($encadrantId);

        $this->view('dashboard/index', [
            'stats' => $stats,
            'totalStagiaires' => $totalStagiaires,
            'candidaturesEnAttente' => $candidaturesEnAttente,
            'livrablesEnAttente' => $livrablesEnAttente,
            'etablissements' => (new Stagiaire())->etablissements(),
            'statsEtablissement' => (new Stagiaire())->statsParEtablissement(),
            'statsGenre' => (new Stagiaire())->statsParGenre(),
            'csrf' => $this->csrfField(),
        ]);
    }

    /**
     * Recherche rapide unifiée : stagiaires déjà acceptés + personnes encore en
     * cours de candidature (Règle 7 : réservé à admin/encadrant/direction).
     */
    public function apiRechercheRapide(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $model = new Stagiaire();
        Response::json($model->rechercheRapide(
            (int)$this->input('page', 1),
            20,
            (string)$this->input('q', ''),
            (string)$this->input('etablissement', '')
        ));
    }
}
