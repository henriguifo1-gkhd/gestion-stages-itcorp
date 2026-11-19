<?php $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié - IT CORP Gestion des stages</title>
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
      <h1>Mot de passe oublié</h1>
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
      <p class="text-muted">
        Renseignez l'adresse email et le numéro de téléphone associés à votre compte : s'ils
        correspondent, vous pourrez immédiatement choisir un nouveau mot de passe.
      </p>
      <form method="post" action="index.php?action=mot-de-passe-oublie/verifier">
        <?= $csrf ?>
        <div class="form-row">
          <label>Adresse email</label>
          <div class="field-icon"><?= icone('mail') ?><input type="email" name="email" required autofocus placeholder="vous@itcorp.co"></div>
        </div>
        <div class="form-row">
          <label>Numéro de téléphone</label>
          <div class="field-icon"><?= icone('phone') ?><input type="tel" name="telephone" required placeholder="074000000"></div>
        </div>
        <button type="submit" class="btn">Vérifier mon identité</button>
      </form>
      <a class="login-back-link" href="index.php?action=login">&larr; Retour à la connexion</a>
    </div>
  </div>
</div>
</body>
</html>
