<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Convention;
use App\Models\Stagiaire;
use App\Models\Stage;

class ConventionController extends Controller
{
    /**
     * Vérifie que l'utilisateur connecté a le droit de consulter/télécharger cette
     * convention : l'admin et la direction n'ont aucune restriction ; un encadrant
     * doit être affecté à un stage rattaché à cette convention ; un stagiaire doit
     * en être le titulaire. (Règle 7 : droits d'accès par rôle.)
     */
    private function autoriseSurConvention(array $convention): bool
    {
        if (Auth::is('admin', 'direction')) {
            return true;
        }
        if (Auth::is('stagiaire')) {
            $monDossier = (new Stagiaire())->findByUtilisateurId((int)Auth::id());
            return $monDossier && (int)$monDossier['id'] === (int)$convention['stagiaire_id'];
        }
        if (Auth::is('encadrant')) {
            $stmt = \App\Core\Database::getInstance()->prepare(
                "SELECT COUNT(*) c FROM stage st
                 JOIN affectation aff ON aff.stage_id = st.id AND aff.date_fin IS NULL
                 WHERE st.convention_id = ? AND aff.encadrant_id = ?"
            );
            $stmt->execute([$convention['id'], Auth::id()]);
            return (int)$stmt->fetch()['c'] > 0;
        }
        return false;
    }

    public function liste(): void
    {
        Auth::requireRole('admin', 'direction');
        $this->view('conventions/liste', ['csrf' => $this->csrfField()]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'direction');
        $model = new Convention();
        Response::json($model->paginer(
            (int)$this->input('page', 1), 20,
            (string)$this->input('q', ''),
            (string)$this->input('statut', '')
        ));
    }

    public function creerForm(): void
    {
        Auth::requireRole('admin');
        $stagiaires = (new Stagiaire())->all('nom ASC');
        $stagiaires = array_filter($stagiaires, fn($s) => $s['statut'] === 'actif');
        $model = new Convention();
        $this->view('conventions/form', [
            'stagiaires' => $stagiaires,
            'stagiaireIdPreselectionne' => (int)$this->input('stagiaire_id', 0),
            'reference' => $model->genererReference(),
            'csrf' => $this->csrfField(),
        ]);
    }

    /** Règle 2 : réutilise les infos du stagiaire, aucune ressaisie nécessaire. */
    public function creer(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();

        $data = [
            'stagiaire_id' => (int)$this->input('stagiaire_id'),
            'reference' => trim((string)$this->input('reference')),
            'type' => trim((string)$this->input('type')),
            'sujet' => trim((string)$this->input('sujet')),
            'date_debut' => $this->input('date_debut'),
            'date_fin' => $this->input('date_fin'),
            'statut' => 'en_attente',
        ];

        if (!$data['stagiaire_id'] || $data['reference'] === '' || $data['sujet'] === '' || !$data['date_debut'] || !$data['date_fin']) {
            $this->flash('error', 'Merci de compléter tous les champs obligatoires de la convention.');
            $this->rememberOldInput($data);
            Response::redirect('index.php?action=conventions/creerForm&stagiaire_id=' . $data['stagiaire_id']);
        }
        $retourErreur = 'index.php?action=conventions/creerForm&stagiaire_id=' . $data['stagiaire_id'];
        if (!strtotime((string)$data['date_debut']) || !strtotime((string)$data['date_fin'])) {
            $this->flash('error', 'Les dates de la convention sont invalides.');
            $this->rememberOldInput($data);
            Response::redirect($retourErreur);
        }
        if (strtotime($data['date_fin']) < strtotime($data['date_debut'])) {
            $this->flash('error', 'La date de fin ne peut pas précéder la date de début.');
            $this->rememberOldInput($data);
            Response::redirect($retourErreur);
        }
        $stagiaire = (new Stagiaire())->find($data['stagiaire_id']);
        if (!$stagiaire) {
            $this->flash('error', 'Stagiaire introuvable.');
            Response::redirect('index.php?action=stagiaires');
        }
        if ($stagiaire['statut'] !== 'actif') {
            $this->flash('error', 'Impossible de créer une convention : ce stagiaire n\'est plus actif (désisté ou archivé).');
            Response::redirect($retourErreur);
        }
        $model = new Convention();
        if ($model->referenceExiste($data['reference'])) {
            $this->flash('error', 'La référence « ' . $data['reference'] . ' » est déjà utilisée par une autre convention. Choisissez-en une autre.');
            $this->rememberOldInput($data);
            Response::redirect($retourErreur);
        }

        $id = $model->insert($data);
        Historique::log('convention', $id, 'creation', 'Convention ' . $data['reference'] . ' créée (statut en_attente).');
        $this->flash('success', 'Convention créée. Elle doit être validée avant de démarrer un stage (Règle 3).');
        Response::redirect('index.php?action=stagiaires/voir&id=' . $data['stagiaire_id']);
    }

    public function valider(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Convention();
        $c = $model->find($id);
        if (!$c) {
            Response::redirect('index.php?action=conventions');
        }
        if ($c['statut'] !== 'en_attente') {
            $this->flash('error', 'Seule une convention « en attente » peut être validée.');
        } else {
            $model->update($id, ['statut' => 'validee']);
            Historique::log('convention', $id, 'validation', 'Convention validée.');
            $this->flash('success', 'Convention validée. Le stage peut désormais être créé.');
        }
        Response::redirect('index.php?action=stagiaires/voir&id=' . $c['stagiaire_id']);
    }

    public function rejeter(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Convention();
        $c = $model->find($id);
        if (!$c) {
            Response::redirect('index.php?action=conventions');
        }
        // Une convention déjà validée (et éventuellement liée à un stage) ne se rejette plus.
        if ($c['statut'] !== 'en_attente') {
            $this->flash('error', 'Seule une convention « en attente » peut être rejetée.');
        } else {
            $model->update($id, ['statut' => 'rejetee']);
            Historique::log('convention', $id, 'rejet', 'Convention rejetée.');
            $this->flash('warning', 'Convention rejetée.');
        }
        Response::redirect('index.php?action=stagiaires/voir&id=' . $c['stagiaire_id']);
    }

    /** Cas limite : convention arrivant à échéance avant la fin réelle du stage -> prolongation. */
    public function prolonger(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $nouvelleDate = trim((string)$this->input('nouvelle_date_fin'));
        $model = new Convention();
        $c = $model->find($id);
        if (!$c) {
            Response::redirect('index.php?action=conventions');
        }
        $retour = 'index.php?action=stagiaires/voir&id=' . $c['stagiaire_id'];
        $ts = strtotime($nouvelleDate);
        if ($nouvelleDate === '' || !$ts) {
            $this->flash('error', 'Merci d\'indiquer une nouvelle date de fin valide.');
            Response::redirect($retour);
        }
        if ($c['statut'] !== 'validee') {
            $this->flash('error', 'Seule une convention validée peut être prolongée.');
            Response::redirect($retour);
        }
        if ($ts <= strtotime($c['date_fin'])) {
            $this->flash('error', 'Une prolongation doit repousser la date de fin au-delà du ' . $c['date_fin'] . '.');
            Response::redirect($retour);
        }
        $nouvelleDate = date('Y-m-d', $ts);
        $model->update($id, ['date_fin' => $nouvelleDate]);
        Historique::log('convention', $id, 'prolongation', 'Date de fin prolongée du ' . $c['date_fin'] . ' au ' . $nouvelleDate);
        $this->flash('success', 'Convention prolongée jusqu\'au ' . $nouvelleDate . '.');
        Response::redirect($retour);
    }

    /**
     * Génère à la volée (sans rien stocker sur le disque) le PDF de la convention,
     * à partir des informations actuellement enregistrées. Comme une convention
     * peut être prolongée, ce PDF n'est jamais mis en cache : il reflète toujours
     * l'état courant plutôt qu'une version figée au moment de la création.
     */
    public function telechargerPdf(): void
    {
        Auth::requireLogin();
        $id = (int)$this->input('id');
        $convention = (new Convention())->find($id);
        if (!$convention) {
            http_response_code(404);
            die('Convention introuvable.');
        }
        if (!$this->autoriseSurConvention($convention)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }

        $stmt = \App\Core\Database::getInstance()->prepare(
            "SELECT nom, prenom, sexe, etablissement, filiere, niveau_etude FROM stagiaire WHERE id = ?"
        );
        $stmt->execute([$convention['stagiaire_id']]);
        $s = $stmt->fetch() ?: [];

        $pdf = $this->construirePdfConvention($convention, $s);
        $this->envoyerFichier('', 'convention_' . $convention['reference'] . '.pdf', $pdf);
    }

    /** Construit le contenu du PDF de la convention (bandeau, titre, clauses, double bloc signature). */
    private function construirePdfConvention(array $c, array $s): string
    {
        $g = \App\Core\Genre::mots($s['sexe'] ?? 'M');
        $civilite = $g['civilite'];
        $etudiantMot = $g['etudiant'];
        $nomAffiche = mb_strtoupper((string)($s['nom'] ?? ''), 'UTF-8') . ' ' . ($s['prenom'] ?? '');
        $filiereSuffixe = !empty($s['filiere']) ? ', en ' . $s['filiere'] : '';
        $niveauSuffixe = !empty($s['niveau_etude']) ? ' (' . $s['niveau_etude'] . ')' : '';

        $dateDebut = $this->dateFr((string)$c['date_debut']);
        $dateFin = $this->dateFr((string)$c['date_fin']);
        $dateGeneration = $this->dateFr(date('Y-m-d'));

        $libellesStatut = [
            'en_attente' => 'En attente de validation',
            'validee' => 'Validée',
            'rejetee' => 'Rejetée',
            'terminee' => 'Terminée',
        ];
        $statutTxt = $libellesStatut[$c['statut']] ?? $c['statut'];
        $statutCouleur = $c['statut'] === 'validee' ? [0.12, 0.56, 0.35] : ($c['statut'] === 'rejetee' ? [0.75, 0.22, 0.17] : [0.72, 0.47, 0.12]);

        $intro = "La présente convention est conclue entre IT CORP, ci-après désigné « l'organisme d'accueil », et "
            . "{$civilite} {$nomAffiche}, {$etudiantMot}{$niveauSuffixe} à " . ($s['etablissement'] ?? '') . "{$filiereSuffixe}, "
            . "ci-après {$g['designe']} « {$g['le_stagiaire']} ».";
        $engagement = "{$g['Le_stagiaire']} s'engage à respecter le règlement intérieur d'IT CORP et à exécuter avec sérieux "
            . "et assiduité les tâches qui lui seront confiées. IT CORP s'engage, de son côté, à assurer l'encadrement "
            . "nécessaire au bon déroulement du stage.";

        $marge = 55;
        $largeurPage = 595.0;
        $largeurTexteMax = $largeurPage - 2 * $marge;

        $c2 = '';
        $c2 .= $this->pdfRectContour(30, 30, 535, 782, [0.75, 0.75, 0.75]);

        // Bandeau d'en-tête
        $c2 .= $this->pdfRect(30, 782, 535, 60, [0.09, 0.22, 0.40]);
        $c2 .= $this->pdfTexteCouleur('IT CORP', 55, 815, 20, [1, 1, 1], 'F2');
        $c2 .= $this->pdfTexteCouleur('Gestion des stages et des stagiaires', 55, 799, 9, [0.85, 0.88, 0.93], 'F1');
        $refTxt = 'N° ' . $c['reference'];
        $refW = $this->largeurTexte($refTxt, 11, true);
        $c2 .= $this->pdfTexteCouleur($refTxt, $largeurPage - 55 - $refW, 808, 11, [1, 1, 1], 'F2');

        // Titre + statut
        $titre = 'CONVENTION DE STAGE';
        $titreW = $this->largeurTexte($titre, 17, true);
        $c2 .= $this->pdfTexte($titre, ($largeurPage - $titreW) / 2, 745, 17, 'F2');
        $statutW = $this->largeurTexte($statutTxt, 10, true);
        $c2 .= $this->pdfTexteCouleur($statutTxt, ($largeurPage - $statutW) / 2, 727, 10, $statutCouleur, 'F2');
        $c2 .= $this->pdfLigne($marge, 715, $largeurPage - $marge, 715);

        // Corps
        $y = 690;
        foreach ($this->decouperTexte($intro, $largeurTexteMax, 11) as $ligne) {
            $c2 .= $this->pdfTexte($ligne, $marge, $y, 11);
            $y -= 17;
        }
        $y -= 14;

        $c2 .= $this->pdfTexte('Il a été convenu ce qui suit :', $marge, $y, 11, 'F2');
        $y -= 24;

        $lignesInfos = [
            'Type de convention' => $c['type'],
            'Sujet du stage' => $c['sujet'],
            'Période' => "du {$dateDebut} au {$dateFin}",
        ];
        foreach ($lignesInfos as $label => $valeur) {
            $c2 .= $this->pdfTexte($label . ' :', $marge, $y, 10.5, 'F2');
            foreach ($this->decouperTexte((string)$valeur, $largeurTexteMax - 150, 10.5) as $i => $ligne) {
                $c2 .= $this->pdfTexte($ligne, $marge + 150, $y, 10.5);
                $y -= 15;
            }
            $y -= 4;
        }
        $y -= 12;

        foreach ($this->decouperTexte($engagement, $largeurTexteMax, 11) as $ligne) {
            $c2 .= $this->pdfTexte($ligne, $marge, $y, 11);
            $y -= 17;
        }

        // Double bloc signature (stagiaire à gauche, IT CORP à droite)
        $blocY = 175.0;
        $fait = 'Fait à Libreville, le ' . $dateGeneration;
        $faitW = $this->largeurTexte($fait, 10);
        $c2 .= $this->pdfTexte($fait, ($largeurPage - $faitW) / 2, $blocY + 30, 10);

        $gaucheX = $marge + 20;
        $c2 .= $this->pdfLigne($gaucheX, $blocY - 30, $gaucheX + 160, $blocY - 30);
        $c2 .= $this->pdfTexte($g['Le_stagiaire'], $gaucheX, $blocY - 46, 10, 'F2');
        $c2 .= $this->pdfTexte($nomAffiche, $gaucheX, $blocY - 61, 9);

        $droiteX = $largeurPage - $marge - 180;
        $c2 .= $this->pdfLigne($droiteX, $blocY - 30, $droiteX + 160, $blocY - 30);
        $c2 .= $this->pdfTexte('Pour IT CORP', $droiteX, $blocY - 46, 10, 'F2');
        $c2 .= $this->pdfTexte('La Direction', $droiteX, $blocY - 61, 9);

        // Pied de page
        $c2 .= $this->pdfLigne($marge, 60, $largeurPage - $marge, 60, [0.75, 0.75, 0.75]);
        $pied = 'IT CORP - Plateforme de gestion des stages - Document généré automatiquement le ' . $dateGeneration;
        $piedW = $this->largeurTexte($pied, 8);
        $c2 .= $this->pdfTexte($pied, ($largeurPage - $piedW) / 2, 45, 8);

        return $this->assemblerPdf($this->pdfObjetsPageSimple($c2));
    }

    /**
     * Téléversement du document signé (scanné) : dépôt du modèle physique une fois
     * signé par les deux parties. Réservé à l'admin, ne remplace pas le PDF généré
     * automatiquement (qui reste toujours disponible), mais l'accompagne.
     */
    public function televerserDocument(): void
    {
        Auth::requireRole('admin');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Convention();
        $convention = $model->find($id);
        if (!$convention) {
            Response::redirect('index.php?action=stagiaires');
        }

        if (empty($_FILES['document']) || ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->flash('error', 'Merci de sélectionner un fichier à téléverser.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }
        if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Le téléversement a échoué. Merci de réessayer.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }

        $extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            $this->flash('error', 'Format non accepté. Merci de téléverser un PDF, JPG ou PNG.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }

        $dir = __DIR__ . '/../../public/uploads/conventions/';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'convention_' . $id . '_signee_' . time() . '.' . $extension;
        if (!move_uploaded_file($_FILES['document']['tmp_name'], $dir . $filename)) {
            $this->flash('error', 'Le fichier n\'a pas pu être enregistré.');
            Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
        }

        // Remplace l'éventuel document précédemment déposé pour cette convention.
        if (!empty($convention['document_path'])) {
            @unlink(__DIR__ . '/../../public/' . $convention['document_path']);
        }
        $model->update($id, ['document_path' => 'uploads/conventions/' . $filename]);
        Historique::log('convention', $id, 'document_televerse', 'Document de convention signé déposé (' . $extension . ').');
        $this->flash('success', 'Document signé enregistré avec succès.');
        Response::redirect('index.php?action=stagiaires/voir&id=' . $convention['stagiaire_id']);
    }

    public function telechargerDocument(): void
    {
        Auth::requireLogin();
        $id = (int)$this->input('id');
        $convention = (new Convention())->find($id);
        if (!$convention || !$convention['document_path']) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        if (!$this->autoriseSurConvention($convention)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        $full = __DIR__ . '/../../public/' . $convention['document_path'];
        if (!file_exists($full)) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        $this->envoyerFichier($full, 'convention_' . $convention['reference'] . '_signee.' . strtolower(pathinfo($full, PATHINFO_EXTENSION)));
    }
}
