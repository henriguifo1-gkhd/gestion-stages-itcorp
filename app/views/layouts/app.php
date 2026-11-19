<?php
use App\Core\Auth;
$u = Auth::user();
$role = $u['role'] ?? '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$roleLabels = ['admin' => 'Administrateur', 'encadrant' => 'Encadrant', 'stagiaire' => 'Stagiaire', 'direction' => 'Direction'];

// Entrée de menu active : premier segment de l'action (ex. « stages/voir » -> « stages »).
$segment = explode('/', (string)($_GET['action'] ?? 'dashboard'))[0] ?: 'dashboard';
if ($segment === 'mon-compte') { $segment = 'mot-de-passe'; }

// Menu latéral, organisé en sections comme le reste de la suite IT CORP.
$menu = [
    'Aperçu' => [
        ['dashboard', 'Tableau de bord', 'dashboard', ['admin', 'encadrant', 'stagiaire', 'direction']],
    ],
    'Recrutement' => [
        ['candidatures', 'Candidatures', 'candidature', ['admin', 'direction']],
        ['stagiaires', 'Stagiaires', 'stagiaire', ['admin', 'encadrant', 'direction']],
        ['conventions', 'Conventions', 'convention', ['admin', 'direction']],
    ],
    'Suivi des stages' => [
        ['stages', 'Stages', 'stage', ['admin', 'encadrant', 'direction']],
        ['livrables', 'Livrables', 'livrable', ['admin', 'encadrant', 'direction']],
        ['attestations', 'Attestations', 'attestation', ['admin', 'direction']],
    ],
    'Administration' => [
        ['utilisateurs', 'Utilisateurs', 'utilisateurs', ['admin']],
    ],
    'Compte' => [
        ['notifications', 'Notifications', 'bell', ['admin', 'encadrant']],
        ['mon-compte/motDePasseForm', 'Mot de passe', 'key', ['admin', 'encadrant', 'stagiaire', 'direction']],
    ],
];

$initiales = mb_strtoupper(mb_substr((string)($u['prenom'] ?? ''), 0, 1) . mb_substr((string)($u['nom'] ?? ''), 0, 1));
$nomComplet = trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));

$notifNonLues = 0;
if (in_array($role, ['admin', 'encadrant'], true)) {
    // Compteur de notifications non lues (un échec ne doit jamais empêcher d'afficher la page).
    try { $notifNonLues = (new \App\Models\Notification())->compterNonLues((int)Auth::id()); } catch (\Throwable $e) { $notifNonLues = 0; }
}
$notifInfo = $notifNonLues ? ' (' . $notifNonLues . ' non lue' . ($notifNonLues > 1 ? 's' : '') . ')' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT CORP - Gestion des stages</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%231e4c7a'/%3E%3Ctext x='16' y='21' font-family='Times New Roman,Times,serif' font-size='13' font-weight='700' fill='white' text-anchor='middle'%3EIT%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js"></script>
</head>
<body>
<div class="app-shell<?= ($_COOKIE['itcorp_menu'] ?? '') === 'repli' ? ' collapsed' : '' ?>" id="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Navigation principale">
    <div class="sidebar-head">
      <?= logo_itcorp('sidebar-logo', true) ?>
      <div class="sidebar-brand"><strong>IT CORP</strong><span>Gestion des stages</span></div>
      <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Réduire ou agrandir le menu" title="Réduire / agrandir le menu"><?= icone('chevrons-left') ?></button>
    </div>
    <nav class="sidebar-nav">
      <?php foreach ($menu as $section => $entrees):
        $visibles = array_filter($entrees, fn($e) => in_array($role, $e[3], true));
        if (!$visibles) { continue; } ?>
        <div class="nav-section"><?= htmlspecialchars($section) ?></div>
        <?php foreach ($visibles as [$cle, $libelle, $ic, $_roles]):
          $actif = ($segment === $cle) || ($cle === 'mon-compte/motDePasseForm' && $segment === 'mot-de-passe'); ?>
          <a class="nav-item<?= $actif ? ' active' : '' ?>" href="index.php?action=<?= $cle ?>" title="<?= htmlspecialchars($libelle) ?>"<?= $actif ? ' aria-current="page"' : '' ?>>
            <?= icone($ic) ?><span class="nav-label"><?= htmlspecialchars($libelle) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <a class="nav-item nav-logout" href="index.php?action=logout" title="Déconnexion"><?= icone('logout') ?><span class="nav-label">Déconnexion</span></a>
    </div>
  </aside>
  <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

  <div class="main-wrap">
    <header class="topbar">
      <button type="button" class="topbar-burger" id="topbar-burger" aria-label="Ouvrir le menu"><?= icone('menu') ?></button>
      <?php if ($role !== 'stagiaire'): ?>
      <form class="topbar-search" method="get" action="index.php" role="search">
        <input type="hidden" name="action" value="dashboard">
        <?= icone('search') ?>
        <input type="search" name="q" placeholder="Rechercher un stagiaire, un candidat…" aria-label="Recherche rapide" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>">
      </form>
      <?php endif; ?>
      <div class="topbar-spacer"></div>
      <div class="topbar-actions">
        <?php if (in_array($role, ['admin', 'encadrant'], true)): ?>
        <a class="icon-btn notif-btn" href="index.php?action=notifications" title="Notifications<?= $notifInfo ?>" aria-label="Notifications<?= $notifInfo ?>">
          <?= icone('bell') ?>
          <?php if ($notifNonLues > 0): ?><span class="notif-count"><?= $notifNonLues > 99 ? '99+' : (int)$notifNonLues ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <div class="profile" id="profile">
          <button type="button" class="profile-btn" id="profile-btn" aria-haspopup="true" aria-expanded="false">
            <span class="avatar"><?= htmlspecialchars($initiales) ?></span>
            <span class="profile-meta"><strong><?= htmlspecialchars($nomComplet) ?></strong><span><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span></span>
            <?= icone('chevron-down') ?>
          </button>
          <div class="profile-menu" id="profile-menu" role="menu">
            <div class="profile-menu-head"><strong><?= htmlspecialchars($nomComplet) ?></strong><span><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span></div>
            <a href="index.php?action=mon-compte/motDePasseForm" role="menuitem"><?= icone('key') ?> Modifier mon mot de passe</a>
            <a class="danger" href="index.php?action=logout" role="menuitem"><?= icone('logout') ?> Se déconnecter</a>
          </div>
        </div>
      </div>
    </header>
    <main class="main">
    <?php if ($flash): ?>
      <div class="toast toast-<?= htmlspecialchars($flash['type']) ?>" id="toast-notification" role="status" aria-live="polite">
        <span class="toast-icon">
          <?php if ($flash['type'] === 'success'): ?>
            <?= icone('check') ?>
          <?php elseif ($flash['type'] === 'error'): ?>
            <?= icone('alert-circle') ?>
          <?php else: ?>
            <?= icone('alert') ?>
          <?php endif; ?>
        </span>
        <span class="toast-message"><?= htmlspecialchars($flash['message']) ?></span>
        <button type="button" class="toast-close" aria-label="Fermer la notification" onclick="fermerNotification()">&times;</button>
      </div>
    <?php endif; ?>
    <?php $content(); ?>
    </main>
  </div>
</div>
</body>
</html>
