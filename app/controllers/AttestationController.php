<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Attestation;
use App\Models\Stage;
use App\Models\Evaluation;
use App\Models\Stagiaire;

class AttestationController extends Controller
{
    public function liste(): void
    {
        Auth::requireRole('admin', 'direction');
        $this->view('attestations/liste', ['csrf' => $this->csrfField()]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'direction');
        Response::json((new Attestation())->paginer((int)$this->input('page', 1), 20, (string)$this->input('q', '')));
    }

    /**
     * Génération de l'attestation.
     * Vérifications : stage arrivé à son terme (statut = termine), pas déjà générée,
     * et cas limite : au moins une évaluation saisie (sinon avertissement bloquant).
     */
    public function generer(): void
    {
        Auth::requireRole('admin', 'direction');
        $this->checkCsrf();
        $stageId = (int)$this->input('id');

        $stageModel = new Stage();
        $stage = $stageModel->find($stageId);
        if (!$stage) {
            Response::redirect('index.php?action=stages');
        }
        if ($stage['statut'] !== 'termine') {
            $this->flash('error', 'L\'attestation ne peut être générée que si le stage est arrivé à son terme.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $attestationModel = new Attestation();
        if ($attestationModel->parStage($stageId)) {
            $this->flash('error', 'Une attestation a déjà été générée pour ce stage.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $evaluations = (new Evaluation())->toutesParStage($stageId);
        if (empty($evaluations)) {
            $this->flash('error', 'Génération impossible : aucune évaluation n\'a été saisie pour ce stage.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }

        $numero = $attestationModel->genererNumero();
        $id = $attestationModel->insert([
            'stage_id' => $stageId,
            'numero' => $numero,
            'date_generation' => date('Y-m-d'),
            'type' => 'Attestation de fin de stage',
            'fichier_path' => null,
            'generee_par' => Auth::id(),
        ]);

        // Génération d'un PDF simple et export dans le dossier du stagiaire.
        $filePath = $this->genererPdf($id, $numero, $stage);
        $attestationModel->update($id, ['fichier_path' => $filePath]);

        Historique::log('attestation', $id, 'generation', "Attestation {$numero} générée pour le stage #{$stageId}.");
        $this->flash('success', "Attestation {$numero} générée et exportée en PDF.");
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    public function telecharger(): void
    {
        Auth::requireLogin();
        $id = (int)$this->input('id');
        $attestation = (new Attestation())->find($id);
        if (!$attestation || !$attestation['fichier_path']) {
            http_response_code(404);
            die('Fichier introuvable.');
        }

        // Règle 7 : seuls admin/direction, l'encadrant affecté au stage, ou le stagiaire
        // titulaire du stage peuvent télécharger l'attestation.
        if (!Auth::is('admin', 'direction')) {
            $stage = (new Stage())->findAvecDetails((int)$attestation['stage_id']);
            $autorise = false;
            if ($stage) {
                if (Auth::is('encadrant')) {
                    $autorise = (int)$stage['encadrant_actuel_id'] === Auth::id();
                } elseif (Auth::is('stagiaire')) {
                    $monDossier = (new Stagiaire())->findByUtilisateurId((int)Auth::id());
                    $autorise = $monDossier && (int)$monDossier['id'] === (int)$stage['stagiaire_id'];
                }
            }
            if (!$autorise) {
                http_response_code(403);
                require __DIR__ . '/../views/errors/403.php';
                return;
            }
        }
        $full = __DIR__ . '/../../public/' . $attestation['fichier_path'];
        if (!file_exists($full)) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        $this->envoyerFichier($full, basename($full));
    }

    /**
     * Génère le PDF de l'attestation, avec une présentation soignée
     * (bandeau d'en-tête, titre, texte justifié aux marges, bloc signature)
     * et un texte correctement accordé au genre du stagiaire (Règle : le
     * champ "sexe" de la fiche stagiaire pilote "étudiant/étudiante",
     * "Monsieur/Madame", "il/elle").
     */
    private function genererPdf(int $attestationId, string $numero, array $stage): string
    {
        $stmt = \App\Core\Database::getInstance()->prepare(
            "SELECT st.date_debut, st.date_fin,
                    s.nom, s.prenom, s.etablissement, s.filiere, s.sexe,
                    cand.poste,
                    u.nom AS encadrant_nom, u.prenom AS encadrant_prenom
             FROM stage st
             JOIN convention c ON c.id = st.convention_id
             JOIN stagiaire s ON s.id = c.stagiaire_id
             LEFT JOIN candidature cand ON cand.id = (
                 SELECT c2.id FROM candidature c2 WHERE c2.stagiaire_id = s.id AND c2.statut = 'acceptee'
                 ORDER BY c2.date_candidature DESC, c2.id DESC LIMIT 1
             )
             LEFT JOIN affectation aff ON aff.stage_id = st.id AND aff.date_fin IS NULL
             LEFT JOIN utilisateur u ON u.id = aff.encadrant_id
             WHERE st.id = ?"
        );
        $stmt->execute([$stage['id']]);
        $d = $stmt->fetch() ?: [];

        $g = \App\Core\Genre::mots($d['sexe'] ?? 'M');
        $civilite = $g['civilite'];
        $etudiantMot = $g['etudiant'];
        $ilElle = $g['il'];

        $nomAffiche = mb_strtoupper((string)($d['nom'] ?? ''), 'UTF-8') . ' ' . ($d['prenom'] ?? '');
        $filiereSuffixe = !empty($d['filiere']) ? ', en ' . $d['filiere'] : '';
        $posteSuffixe = !empty($d['poste']) ? ', en qualité de ' . $d['poste'] : '';
        $encadrantSuffixe = !empty($d['encadrant_nom'])
            ? ', sous la supervision de ' . trim(($d['encadrant_prenom'] ?? '') . ' ' . $d['encadrant_nom'])
            : '';

        $dateDebut = $this->dateFr((string)($d['date_debut'] ?? ''));
        $dateFin = $this->dateFr((string)($d['date_fin'] ?? ''));
        $dateGeneration = $this->dateFr(date('Y-m-d'));

        $paragraphe1 = "La Direction d'IT CORP atteste par la présente que {$civilite} {$nomAffiche}, {$etudiantMot} à "
            . ($d['etablissement'] ?? '') . "{$filiereSuffixe}, a effectué un stage au sein de notre structure du "
            . "{$dateDebut} au {$dateFin}{$posteSuffixe}.";
        $paragraphe2 = "Durant cette période, {$ilElle} a fait preuve de sérieux, d'assiduité et d'un bon esprit "
            . "d'équipe dans l'exécution des tâches qui lui ont été confiées{$encadrantSuffixe}.";
        $paragraphe3 = "En foi de quoi, la présente attestation lui est délivrée pour servir et valoir ce que de droit.";

        // --- mise en page -------------------------------------------------
        $marge = 55;
        $largeurPage = 595.0;
        $largeurTexteMax = $largeurPage - 2 * $marge;

        $c = '';
        $c .= $this->pdfRectContour(30, 30, 535, 782, [0.75, 0.75, 0.75]);

        // Bandeau d'en-tête
        $c .= $this->pdfRect(30, 782, 535, 60, [0.09, 0.22, 0.40]);
        $c .= $this->pdfTexteCouleur('IT CORP', 55, 815, 20, [1, 1, 1], 'F2');
        $c .= $this->pdfTexteCouleur('Gestion des stages et des stagiaires', 55, 799, 9, [0.85, 0.88, 0.93], 'F1');
        $refTxt = 'N° ' . $numero;
        $refW = $this->largeurTexte($refTxt, 11, true);
        $c .= $this->pdfTexteCouleur($refTxt, $largeurPage - 55 - $refW, 808, 11, [1, 1, 1], 'F2');

        // Titre
        $titre = 'ATTESTATION DE FIN DE STAGE';
        $titreW = $this->largeurTexte($titre, 17, true);
        $c .= $this->pdfTexte($titre, ($largeurPage - $titreW) / 2, 745, 17, 'F2');
        $c .= $this->pdfLigne($marge, 730, $largeurPage - $marge, 730);

        // Corps du texte
        $y = 702;
        foreach ([$paragraphe1, $paragraphe2] as $texte) {
            foreach ($this->decouperTexte($texte, $largeurTexteMax, 11) as $ligne) {
                $c .= $this->pdfTexte($ligne, $marge, $y, 11);
                $y -= 17;
            }
            $y -= 11;
        }
        $y -= 5;
        foreach ($this->decouperTexte($paragraphe3, $largeurTexteMax, 11) as $ligne) {
            $c .= $this->pdfTexte($ligne, $marge, $y, 11);
            $y -= 17;
        }

        // Bloc signature (bas de page, aligné à droite)
        $largeurBloc = 210.0;
        $blocX = $largeurPage - $marge - $largeurBloc;
        $blocY = 190.0;
        $l1 = 'Fait à Libreville, le ' . $dateGeneration;
        $c .= $this->pdfTexte($l1, $blocX + $largeurBloc - $this->largeurTexte($l1, 10), $blocY, 10);
        $c .= $this->pdfLigne($blocX + $largeurBloc - 160, $blocY - 42, $blocX + $largeurBloc, $blocY - 42);
        $l2 = 'La Direction';
        $c .= $this->pdfTexte($l2, $blocX + $largeurBloc - $this->largeurTexte($l2, 10, true), $blocY - 58, 10, 'F2');
        $l3 = 'IT CORP';
        $c .= $this->pdfTexte($l3, $blocX + $largeurBloc - $this->largeurTexte($l3, 10), $blocY - 73, 10);

        // Pied de page
        $c .= $this->pdfLigne($marge, 60, $largeurPage - $marge, 60, [0.75, 0.75, 0.75]);
        $pied = 'IT CORP - Plateforme de gestion des stages - Document généré automatiquement le ' . $dateGeneration;
        $piedW = $this->largeurTexte($pied, 8);
        $c .= $this->pdfTexte($pied, ($largeurPage - $piedW) / 2, 45, 8);

        $pdf = $this->assemblerPdf($this->pdfObjetsPageSimple($c));

        $dir = __DIR__ . '/../../public/uploads/attestations/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'attestation_' . $attestationId . '_' . $numero . '.pdf';
        file_put_contents($dir . $filename, $pdf);
        return 'uploads/attestations/' . $filename;
    }
}
