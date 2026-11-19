<h1><?= icone('key') ?> Modifier mon mot de passe</h1>
<div class="card" style="max-width:440px;">
  <form method="post" action="index.php?action=mon-compte/motDePasse">
    <?= $csrf ?>
    <div class="form-row">
      <label>Ancien mot de passe *</label>
      <input type="password" name="ancien_mot_de_passe" required autocomplete="current-password">
    </div>
    <div class="form-row">
      <label>Nouveau mot de passe *</label>
      <input type="password" name="nouveau_mot_de_passe" required minlength="6" autocomplete="new-password">
      <p class="text-muted" style="margin:4px 0 0;font-size:.82rem;"><?= htmlspecialchars(\App\Models\Utilisateur::MDP_REGLES) ?></p>
    </div>
    <div class="form-row">
      <label>Confirmation du nouveau mot de passe *</label>
      <input type="password" name="confirmation_mot_de_passe" required minlength="6" autocomplete="new-password">
    </div>
    <button class="btn" type="submit">Enregistrer le nouveau mot de passe</button>
    <a class="btn btn-outline" href="index.php?action=dashboard">Annuler</a>
  </form>
</div>
