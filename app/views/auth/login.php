<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion - IT CORP Gestion des stages</title>
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
      <h1>Connexion</h1>
      <div class="subtitle">Plateforme de gestion des stages et des stagiaires</div>
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
      <!-- novalidate : sur mobile, la bulle de validation du navigateur est souvent invisible et
           bloque l'envoi sans explication ; le serveur vérifie les champs et affiche un message clair. -->
      <form method="post" action="index.php?action=login" novalidate>
        <?= $csrf ?>
        <div class="form-row">
          <label>Adresse email</label>
          <div class="field-icon"><?= icone('mail') ?><input type="email" name="email" inputmode="email" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus placeholder="vous@itcorp.co"></div>
        </div>
        <div class="form-row">
          <label>Mot de passe</label>
          <div class="password-field">
            <?= icone('lock') ?>
            <input type="password" name="mot_de_passe" autocomplete="current-password" required placeholder="••••••••">
            <button type="button" class="password-toggle" aria-label="Afficher le mot de passe" aria-pressed="false">
              <svg class="icon-oeil-ouvert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-oeil-barre" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 7 11 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.53 13.53 0 0 0 1 11s4 7 11 7a9.26 9.26 0 0 0 5.39-1.61"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div class="login-links">
          <a href="index.php?action=mot-de-passe-oublie">Mot de passe oublié ?</a>
        </div>
        <button type="submit" class="btn">Se connecter</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
