<?php
/**
 * Front controller unique de l'application.
 * Toutes les requêtes passent par ici : index.php?action=controleur/methode
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Response.php';
require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/core/Auth.php';
require __DIR__ . '/../app/core/Router.php';
require __DIR__ . '/../app/core/Controller.php';
require __DIR__ . '/../app/core/Historique.php';
require __DIR__ . '/../app/core/Icones.php';
require __DIR__ . '/../app/core/Mailer.php';
require __DIR__ . '/../app/core/Genre.php';

// --- Autoload simple des Models et Controllers ---------------------------
spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\Models\\' => __DIR__ . '/../app/models/',
        'App\\Controllers\\' => __DIR__ . '/../app/controllers/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = $dir . substr($class, strlen($prefix)) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }
    }
});

use App\Core\Auth;
use App\Core\Router;

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

/*
 * Filet de sécurité : une exception non interceptée (règle métier refusée par un déclencheur
 * de la base, contrainte d'unicité...) ne doit jamais afficher une « Fatal error » brute.
 * Sur un formulaire (POST), le message est renvoyé à l'utilisateur sur la page précédente ;
 * sinon une page d'erreur sobre est affichée. Le détail technique part dans le journal PHP.
 */
set_exception_handler(function (\Throwable $e) {
    error_log('[gestion-stages] ' . get_class($e) . ' : ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $message = 'Une erreur inattendue est survenue. L\'opération n\'a pas été enregistrée.';
    if ($e instanceof \PDOException) {
        $info = $e->errorInfo ?? [];
        $sqlState = (string)($info[0] ?? $e->getCode());
        $codeMysql = (int)($info[1] ?? 0);
        if ($sqlState === '45000' && !empty($info[2])) {
            // Message rédigé dans 02_triggers.sql : déjà destiné à l'utilisateur.
            $message = 'Opération refusée : ' . $info[2] . '.';
        } elseif ($codeMysql === 1062) {
            $message = 'Opération refusée : cette valeur existe déjà (doublon).';
        } elseif (in_array($codeMysql, [1451, 1452], true)) {
            $message = 'Opération refusée : cet élément est lié à d\'autres données.';
        } elseif ($codeMysql === 3819) {
            $message = 'Opération refusée : une des valeurs saisies est incohérente (par exemple une date de fin avant la date de début).';
        }
    }

    $ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    if (!headers_sent()) {
        if ($ajax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
            return;
        }
        $retour = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $retourInterne = $retour !== '' && parse_url($retour, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $retourInterne) {
            \App\Core\Auth::start();
            $_SESSION['flash'] = ['type' => 'error', 'message' => $message];
            header('Location: ' . $retour);
            return;
        }
        http_response_code(500);
    }
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title>'
        . '<link rel="stylesheet" href="assets/css/style.css"></head><body><div class="card" style="max-width:640px;margin:60px auto;">'
        . '<h1>Erreur</h1><p>' . htmlspecialchars($message) . '</p>'
        . '<p><a class="btn" href="index.php?action=dashboard">Retour au tableau de bord</a></p></div></body></html>';
});

Auth::start();
Auth::tryRememberedLogin();

$action = $_GET['action'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

$router = new Router();

// Authentification
$router->map('login', $method === 'POST' ? 'AuthController@login' : 'AuthController@loginForm');
$router->map('logout', 'AuthController@logout');

// Mot de passe oublié (vérification par email + téléphone, sans serveur d'email/SMS)
$router->map('mot-de-passe-oublie', 'MotDePasseOublieController@form');
$router->map('mot-de-passe-oublie/verifier', 'MotDePasseOublieController@verifier');
$router->map('mot-de-passe-oublie/reinitialiserForm', 'MotDePasseOublieController@reinitialiserForm');
$router->map('mot-de-passe-oublie/reinitialiser', 'MotDePasseOublieController@reinitialiser');

// Tableau de bord
$router->map('dashboard', 'DashboardController@index');
$router->map('dashboard/apiRechercheRapide', 'DashboardController@apiRechercheRapide');

// Mon compte (auto-service, tous rôles)
$router->map('mon-compte/motDePasseForm', 'MonCompteController@motDePasseForm');
$router->map('mon-compte/motDePasse', 'MonCompteController@motDePasse');

// Candidatures
$router->map('candidatures', 'CandidatureController@liste');
$router->map('candidatures/apiListe', 'CandidatureController@apiListe');
$router->map('candidatures/creerForm', 'CandidatureController@creerForm');
$router->map('candidatures/creer', 'CandidatureController@creer');
$router->map('candidatures/accepter', 'CandidatureController@accepter');
$router->map('candidatures/refuser', 'CandidatureController@refuser');
$router->map('candidatures/desister', 'CandidatureController@desister');
$router->map('candidatures/mailForm', 'CandidatureController@mailForm');
$router->map('candidatures/envoyerMail', 'CandidatureController@envoyerMail');

// Stagiaires
$router->map('stagiaires', 'StagiaireController@liste');
$router->map('stagiaires/apiListe', 'StagiaireController@apiListe');
$router->map('stagiaires/voir', 'StagiaireController@voir');
$router->map('stagiaires/editerForm', 'StagiaireController@editerForm');
$router->map('stagiaires/editer', 'StagiaireController@editer');
$router->map('stagiaires/supprimer', 'StagiaireController@supprimer');
$router->map('stagiaires/lierCompte', 'StagiaireController@lierCompte');
$router->map('stagiaires/delierCompte', 'StagiaireController@delierCompte');

// Conventions
$router->map('conventions', 'ConventionController@liste');
$router->map('conventions/apiListe', 'ConventionController@apiListe');
$router->map('conventions/creerForm', 'ConventionController@creerForm');
$router->map('conventions/creer', 'ConventionController@creer');
$router->map('conventions/valider', 'ConventionController@valider');
$router->map('conventions/rejeter', 'ConventionController@rejeter');
$router->map('conventions/prolonger', 'ConventionController@prolonger');
$router->map('conventions/telechargerPdf', 'ConventionController@telechargerPdf');
$router->map('conventions/televerserDocument', 'ConventionController@televerserDocument');
$router->map('conventions/telechargerDocument', 'ConventionController@telechargerDocument');

// Stages
$router->map('stages', 'StageController@liste');
$router->map('stages/apiListe', 'StageController@apiListe');
$router->map('stages/creerForm', 'StageController@creerForm');
$router->map('stages/creer', 'StageController@creer');
$router->map('stages/voir', 'StageController@voir');
$router->map('stages/affecter', 'StageController@affecter');
$router->map('stages/demarrer', 'StageController@demarrer');
$router->map('stages/cloturer', 'StageController@cloturer');
$router->map('stages/interrompre', 'StageController@interrompre');

// Livrables
$router->map('livrables', 'LivrableController@liste');
$router->map('livrables/apiListe', 'LivrableController@apiListe');
$router->map('livrables/apiListeParStage', 'LivrableController@apiListeParStage');
$router->map('livrables/ajouterEcheance', 'LivrableController@ajouterEcheance');
$router->map('livrables/deposer', 'LivrableController@deposer');
$router->map('livrables/valider', 'LivrableController@valider');
$router->map('livrables/rejeter', 'LivrableController@rejeter');
$router->map('livrables/telecharger', 'LivrableController@telecharger');
$router->map('livrables/archiver', 'LivrableController@archiver');
$router->map('livrables/restaurer', 'LivrableController@restaurer');

// Évaluations
$router->map('evaluations/creer', 'EvaluationController@creer');
$router->map('evaluations/modifier', 'EvaluationController@modifier');

// Attestations
$router->map('attestations', 'AttestationController@liste');
$router->map('attestations/apiListe', 'AttestationController@apiListe');
$router->map('attestations/generer', 'AttestationController@generer');
$router->map('attestations/telecharger', 'AttestationController@telecharger');

// Notifications (admin, encadrant)
$router->map('notifications', 'NotificationController@liste');
$router->map('notifications/apiListe', 'NotificationController@apiListe');
$router->map('notifications/ouvrir', 'NotificationController@ouvrir');
$router->map('notifications/toutLu', 'NotificationController@toutLu');

// Utilisateurs
$router->map('utilisateurs', 'UtilisateurController@liste');
$router->map('utilisateurs/apiListe', 'UtilisateurController@apiListe');
$router->map('utilisateurs/creerForm', 'UtilisateurController@creerForm');
$router->map('utilisateurs/creer', 'UtilisateurController@creer');
$router->map('utilisateurs/editerForm', 'UtilisateurController@editerForm');
$router->map('utilisateurs/editer', 'UtilisateurController@editer');
$router->map('utilisateurs/desactiver', 'UtilisateurController@desactiver');
$router->map('utilisateurs/activer', 'UtilisateurController@activer');

$router->dispatch($action);
