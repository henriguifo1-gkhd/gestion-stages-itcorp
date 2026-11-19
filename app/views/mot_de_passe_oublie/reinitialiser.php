<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouveau mot de passe - IT CORP Gestion des stages</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%2300AEEF'/%3E%3Ctext x='16' y='21' font-family='Times New Roman,Times,serif' font-size='13' font-weight='700' fill='white' text-anchor='middle'%3EIT%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/app.js"></script>
</head>
<body>
<div class="login-page">
  <div class="login-diamond" aria-hidden="true"><i></i><i></i><i></i></div>

  <div class="login-center">
    <div class="login-card">
      <?= logo_itcorp('login-logo', true) ?>
      <div class="login-brand-word"><b>IT</b>-CORP</div>
      <h1>Nouveau mot de passe</h1>
      <div class="subtitle">Identité vérifiée : <?= htmlspecialchars($utilisateur['prenom'] . ' ' . $utilisateur['nom']) ?></div>
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
      <form method="post" action="index.php?action=mot-de-passe-oublie/reinitialiser">
        <?= $csrf ?>
        <div class="form-row">
          <label>Nouveau mot de passe</label>
          <div class="field-icon"><?= icone('lock') ?><input type="password" name="nouveau_mot_de_passe" required minlength="6" placeholder="••••••••" autofocus></div>
          <p class="text-muted" style="margin:4px 0 0;font-size:.82rem;"><?= htmlspecialchars(\App\Models\Utilisateur::MDP_REGLES) ?></p>
        </div>
        <div class="form-row">
          <label>Confirmer le mot de passe</label>
          <div class="field-icon"><?= icone('lock') ?><input type="password" name="confirmation_mot_de_passe" required minlength="6" placeholder="••••••••"></div>
        </div>
        <button type="submit" class="btn">Réinitialiser le mot de passe</button>
      </form>
      <a class="login-back-link" href="index.php?action=login">&larr; Retour à la connexion</a>
    </div>
  </div>
</div>
</body>
</html>
