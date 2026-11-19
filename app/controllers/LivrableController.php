<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Historique;
use App\Core\Response;
use App\Models\Livrable;
use App\Models\Stage;
use App\Models\Stagiaire;

class LivrableController extends Controller
{
    /** Extensions acceptées pour le dépôt d'un livrable. */
    private const EXTENSIONS_AUTORISEES = ['pdf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'zip'];

    /**
     * Vérifie que l'utilisateur connecté a le droit d'agir sur ce stage :
     * l'admin et la direction n'ont aucune restriction ; un encadrant doit être
     * celui actuellement affecté au stage ; un stagiaire doit être le titulaire du stage.
     * (Règle 7 : droits d'accès par rôle, appliqués au niveau applicatif.)
     */
    private function autoriseSurStage(array $stage): bool
    {
        if (Auth::is('admin', 'direction')) {
            return true;
        }
        if (Auth::is('encadrant')) {
            return (int)$stage['encadrant_actuel_id'] === Auth::id();
        }
        if (Auth::is('stagiaire')) {
            $monDossier = (new Stagiaire())->findByUtilisateurId((int)Auth::id());
            return $monDossier && (int)$monDossier['id'] === (int)$stage['stagiaire_id'];
        }
        return false;
    }
    /** Convertit une valeur php.ini (« 2M », « 8M »…) en octets. */
    private static function enOctets(string $v): int
    {
        $v = trim($v);
        $n = (int)$v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            // no break
            case 'm': $n *= 1024;
            // no break
            case 'k': $n *= 1024;
        }
        return $n;
    }

    /** Taille maximale réellement acceptée par le serveur pour un fichier, en clair (ex. « 2 Mo »). */
    private static function limiteUpload(): string
    {
        $o = min(self::enOctets((string)ini_get('upload_max_filesize')), self::enOctets((string)ini_get('post_max_size')) ?: PHP_INT_MAX);
        return $o >= 1048576 ? round($o / 1048576, 1) . ' Mo' : round($o / 1024) . ' Ko';
    }

    /** Explication claire d'un échec de téléversement PHP. */
    private static function messageErreurUpload(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Le fichier est trop volumineux : le serveur n\'accepte pas plus de ' . self::limiteUpload()
                     . ' par fichier. Réduisez le fichier (compression) ou demandez à l\'administrateur d\'augmenter upload_max_filesize et post_max_size dans php.ini.';
            case UPLOAD_ERR_PARTIAL:
                return 'Le fichier n\'a été reçu que partiellement (connexion interrompue). Veuillez réessayer.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Le serveur n\'a pas de dossier temporaire pour recevoir le fichier (upload_tmp_dir dans php.ini).';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Le serveur n\'a pas pu écrire le fichier sur le disque. Contactez l\'administrateur.';
            default:
                return 'Le fichier n\'a pas pu être reçu (code ' . $code . '). Veuillez réessayer.';
        }
    }

    public function liste(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        $this->view('livrables/liste', ['csrf' => $this->csrfField()]);
    }

    public function apiListe(): void
    {
        Auth::requireRole('admin', 'encadrant', 'direction');
        // Un encadrant ne voit que les livrables des stagiaires dont il est l'encadrant actuel.
        $encadrantId = Auth::is('encadrant') ? Auth::id() : null;
        Response::json((new Livrable())->paginer((int)$this->input('page', 1), 20, (string)$this->input('statut', ''), $encadrantId));
    }

    public function apiListeParStage(): void
    {
        Auth::requireLogin();
        $stageId = (int)$this->input('stage_id');
        $stage = (new Stage())->findAvecDetails($stageId);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            Response::json(['error' => 'Accès refusé.'], 403);
        }
        Response::json((new Livrable())->paginerParStage($stageId, (int)$this->input('page', 1), 20));
    }

    public function ajouterEcheance(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->checkCsrf();
        $stageId = (int)$this->input('stage_id');
        $stage = (new Stage())->findAvecDetails($stageId);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            // Pas de retour vers le détail du stage : il est lui-même interdit à cet utilisateur (page 403).
            $this->flash('error', 'Vous ne pouvez gérer que les livrables des stages qui vous sont affectés.');
            Response::redirect('index.php?action=stages');
        }
        $titre = trim((string)$this->input('titre'));
        $dateEcheance = trim((string)$this->input('date_echeance'));
        if ($titre === '' || $dateEcheance === '' || !strtotime($dateEcheance)) {
            $this->flash('error', 'Merci de renseigner le titre et une date d\'échéance valide.');
            Response::redirect('index.php?action=stages/voir&id=' . $stageId);
        }
        // hors_delai est une colonne générée (STORED) par la base : jamais dans l'INSERT.
        $id = (new Livrable())->insert([
            'stage_id' => $stageId,
            'titre' => mb_substr($titre, 0, 150, 'UTF-8'),
            'date_echeance' => date('Y-m-d', strtotime($dateEcheance)),
            'statut_validation' => 'en_attente',
        ]);
        Historique::log('livrable', $id, 'creation', 'Échéance de livrable ajoutée.');
        $this->flash('success', 'Échéance de livrable ajoutée.');
        Response::redirect('index.php?action=stages/voir&id=' . $stageId);
    }

    /**
     * Le stagiaire dépose son propre livrable (et lui seul : ni l'admin ni l'encadrant ne
     * déposent à sa place, pour garder la traçabilité de qui a réellement fourni le document).
     * Cas limite : hors délai accepté mais marqué.
     */
    public function deposer(): void
    {
        Auth::requireRole('stagiaire');
        // Fichier plus gros que post_max_size : PHP vide alors tout le formulaire (y compris le jeton).
        if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->flash('error', 'Le fichier est trop volumineux : le serveur n\'accepte pas plus de ' . self::limiteUpload() . '. Réduisez le fichier ou demandez à l\'administrateur d\'augmenter post_max_size et upload_max_filesize dans php.ini.');
            Response::redirect('index.php?action=dashboard');
        }
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Livrable();
        $livrable = $model->find($id);
        if (!$livrable) {
            Response::redirect('index.php?action=dashboard');
        }
        if (!empty($livrable['archive'])) {
            $this->flash('error', 'Ce livrable a été supprimé (archivé) : il ne peut plus être déposé.');
            Response::redirect('index.php?action=dashboard');
        }
        if (($livrable['statut_validation'] ?? '') === 'valide') {
            $this->flash('error', 'Ce livrable a déjà été validé : il ne peut plus être remplacé.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        $stage = (new Stage())->findAvecDetails((int)$livrable['stage_id']);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            $this->flash('error', 'Vous ne pouvez déposer que les livrables de votre propre stage.');
            Response::redirect('index.php?action=dashboard');
        }

        $fichierPath = null;
        // Le dépôt doit comporter un fichier : sans lui, le bouton « Ouvrir » n'aurait rien à ouvrir.
        if (empty($_FILES['fichier']['name'])) {
            $this->flash('error', 'Veuillez joindre le fichier du livrable avant de le déposer.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        if (($_FILES['fichier']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->flash('error', self::messageErreurUpload((int)$_FILES['fichier']['error']));
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        if (!empty($_FILES['fichier']['name'])) {
            // Liste blanche d'extensions : un fichier exécutable (.php, .phtml...) déposé dans
            // le dossier public pourrait sinon être exécuté par le serveur web.
            $extension = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, self::EXTENSIONS_AUTORISEES, true)) {
                $this->flash('error', 'Format de fichier non accepté. Formats autorisés : ' . implode(', ', self::EXTENSIONS_AUTORISEES) . '.');
                Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
            }
            $dir = __DIR__ . '/../../public/uploads/livrables/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                $this->flash('error', 'Le dossier public/uploads/livrables est introuvable ou protégé en écriture : le fichier ne peut pas être enregistré. Contactez l\'administrateur.');
                Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
            }
            $safe = 'liv_' . $id . '_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['fichier']['name']));
            if (move_uploaded_file($_FILES['fichier']['tmp_name'], $dir . $safe)) {
                $fichierPath = 'uploads/livrables/' . $safe;
            }
        }
        if ($fichierPath === null) {
            $this->flash('error', 'Le fichier n\'a pas pu être enregistré. Veuillez réessayer.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }

        $model->deposer($id, $fichierPath);
        $updated = $model->find($id);
        $msg = $updated['hors_delai'] ? 'Livrable déposé, mais HORS DÉLAI (échéance dépassée). L\'encadrant en sera informé.' : 'Livrable déposé avec succès.';
        Historique::log('livrable', $id, 'depot', $msg);

        // Le dépôt est signalé à l'encadrant actuel du stage et aux administrateurs. Un échec de
        // notification (table absente avant migration, par exemple) ne doit jamais faire perdre le dépôt.
        try {
            (new \App\Models\Notification())->notifierDepotLivrable(
                (int)$livrable['stage_id'],
                trim($stage['stagiaire_prenom'] . ' ' . $stage['stagiaire_nom']),
                (string)$livrable['titre'],
                (bool)$updated['hors_delai'],
                $stage['encadrant_actuel_id'] !== null ? (int)$stage['encadrant_actuel_id'] : null
            );
        } catch (\Throwable $e) {
            error_log('Notification de dépôt non enregistrée : ' . $e->getMessage());
        }
        $this->flash($updated['hors_delai'] ? 'warning' : 'success', $msg);
        Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
    }

    public function valider(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Livrable();
        $livrable = $model->find($id);
        if (!$livrable) {
            Response::redirect('index.php?action=dashboard');
        }
        $stage = (new Stage())->findAvecDetails((int)$livrable['stage_id']);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            $this->flash('error', 'Vous ne pouvez valider que les livrables des stages qui vous sont affectés.');
            Response::redirect('index.php?action=livrables');
        }
        if (!empty($livrable['archive'])) {
            $this->flash('error', 'Ce livrable est archivé : restaurez-le avant de le valider.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        // Cas limite : un livrable non déposé ne peut pas être validé (contrainte chk_livrable_validation).
        if (empty($livrable['date_depot'])) {
            $this->flash('error', 'Impossible de valider : ce livrable n\'a pas encore été déposé.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        // La contrainte chk_livrable_validation impose valide_par + date_validation dès que
        // statut_validation passe à 'valide' ou 'rejete', et le trigger exige que valide_par
        // désigne un compte de rôle "encadrant" : quand un admin agit, on rattache donc l'action
        // à l'encadrant actuellement affecté au stage (même logique que pour les évaluations).
        $valideParId = Auth::is('encadrant') ? Auth::id() : ($stage['encadrant_actuel_id'] ?? null);
        if (!$valideParId) {
            $this->flash('error', "Impossible de valider : aucun encadrant n'est actuellement affecté à ce stage.");
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        $model->update($id, [
            'statut_validation' => 'valide',
            'commentaire' => $this->input('commentaire', $livrable['commentaire'] ?? null),
            'valide_par' => $valideParId,
            'date_validation' => date('Y-m-d H:i:s'),
        ]);
        Historique::log('livrable', $id, 'validation', 'Livrable validé par l\'encadrant.');
        $this->flash('success', 'Livrable validé.');
        Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
    }

    public function rejeter(): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Livrable();
        $livrable = $model->find($id);
        if (!$livrable) {
            Response::redirect('index.php?action=dashboard');
        }
        $stage = (new Stage())->findAvecDetails((int)$livrable['stage_id']);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            $this->flash('error', 'Vous ne pouvez rejeter que les livrables des stages qui vous sont affectés.');
            Response::redirect('index.php?action=livrables');
        }
        if (!empty($livrable['archive'])) {
            $this->flash('error', 'Ce livrable est archivé : restaurez-le avant de le rejeter.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        // Cas limite : un livrable non déposé ne peut pas être rejeté (contrainte chk_livrable_validation).
        if (empty($livrable['date_depot'])) {
            $this->flash('error', 'Impossible de rejeter : ce livrable n\'a pas encore été déposé.');
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        $valideParId = Auth::is('encadrant') ? Auth::id() : ($stage['encadrant_actuel_id'] ?? null);
        if (!$valideParId) {
            $this->flash('error', "Impossible de rejeter : aucun encadrant n'est actuellement affecté à ce stage.");
            Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
        }
        $model->update($id, [
            'statut_validation' => 'rejete',
            'commentaire' => $this->input('commentaire', $livrable['commentaire'] ?? null),
            'valide_par' => $valideParId,
            'date_validation' => date('Y-m-d H:i:s'),
        ]);
        Historique::log('livrable', $id, 'rejet', 'Livrable rejeté par l\'encadrant.');
        $this->flash('warning', 'Livrable rejeté.');
        Response::redirect('index.php?action=stages/voir&id=' . $livrable['stage_id']);
    }

    /**
     * « Supprime » un livrable en l'ARCHIVANT (déposé ou non, ancien ou récent) : il disparaît
     * des listes mais reste en base avec son fichier, et peut être restauré. Rien n'est effacé,
     * ce qui conserve la traçabilité. Réservé à l'admin et à l'encadrant affecté au stage
     * (jamais au stagiaire, qui ne doit pas pouvoir faire disparaître un dépôt qu'il a fait).
     */
    public function archiver(): void
    {
        $this->changerArchivage(true);
    }

    public function restaurer(): void
    {
        $this->changerArchivage(false);
    }

    private function changerArchivage(bool $archiver): void
    {
        Auth::requireRole('admin', 'encadrant');
        $this->checkCsrf();
        $id = (int)$this->input('id');
        $model = new Livrable();
        $livrable = $model->find($id);
        if (!$livrable) {
            Response::redirect('index.php?action=dashboard');
        }
        $stage = (new Stage())->findAvecDetails((int)$livrable['stage_id']);
        $retour = $this->input('retour') === 'liste' ? 'index.php?action=livrables' : 'index.php?action=stages/voir&id=' . $livrable['stage_id'];
        if (!$stage || !$this->autoriseSurStage($stage)) {
            $this->flash('error', 'Vous ne pouvez gérer que les livrables des stages qui vous sont affectés.');
            Response::redirect('index.php?action=livrables');
        }
        if ((bool)$livrable['archive'] === $archiver) {
            $this->flash('warning', $archiver ? 'Ce livrable est déjà archivé.' : 'Ce livrable n\'est pas archivé.');
            Response::redirect($retour);
        }
        if ($archiver) {
            $model->archiver($id, (int)Auth::id());
            Historique::log('livrable', $id, 'archivage', 'Livrable "' . $livrable['titre'] . '" supprimé (archivé' . ($livrable['date_depot'] ? ', dépôt et fichier conservés' : ', aucun dépôt') . ').');
            $this->flash('success', 'Livrable supprimé (archivé). Il reste consultable dans le filtre « Archivés » de la liste des livrables.');
        } else {
            $model->restaurer($id);
            Historique::log('livrable', $id, 'restauration', 'Livrable "' . $livrable['titre'] . '" restauré.');
            $this->flash('success', 'Livrable restauré.');
        }
        Response::redirect($retour);
    }

    /**
     * Ouverture/téléchargement du fichier déposé pour un livrable.
     * Mêmes droits d'accès que pour la gestion du livrable (Règle 7) :
     * admin/direction, l'encadrant affecté au stage, ou le stagiaire titulaire.
     */
    public function telecharger(): void
    {
        Auth::requireLogin();
        $id = (int)$this->input('id');
        $livrable = (new Livrable())->find($id);
        if (!$livrable || !$livrable['fichier_path']) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        $stage = (new Stage())->findAvecDetails((int)$livrable['stage_id']);
        if (!$stage || !$this->autoriseSurStage($stage)) {
            http_response_code(403);
            require __DIR__ . '/../views/errors/403.php';
            return;
        }
        if (!empty($livrable['archive']) && Auth::is('stagiaire')) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        $full = __DIR__ . '/../../public/' . $livrable['fichier_path'];
        if (!file_exists($full)) {
            http_response_code(404);
            die('Fichier introuvable.');
        }
        // Nom proposé : celui d'origine, sans le préfixe technique « liv_<id>_<horodatage>_ ».
        $nom = preg_replace('/^liv_\d+_\d+_/', '', basename($full)) ?: basename($full);
        $this->envoyerFichier($full, $nom);
    }
}
