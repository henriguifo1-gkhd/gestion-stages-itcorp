<h1><?= icone('utilisateurs') ?> Nouvel utilisateur</h1>
<div class="card" style="max-width:520px;">
  <form method="post" action="index.php?action=utilisateurs/creer">
    <?= $csrf ?>
    <div class="form-grid">
      <div class="form-row"><label>Nom *</label><input name="nom" value="<?= htmlspecialchars($old['nom'] ?? '') ?>" required></div>
      <div class="form-row"><label>Prénom *</label><input name="prenom" value="<?= htmlspecialchars($old['prenom'] ?? '') ?>" required></div>
    </div>
    <div class="form-row"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required></div>
    <div class="form-row">
      <label>Téléphone</label>
      <input type="tel" name="telephone" value="<?= htmlspecialchars($old['telephone'] ?? '') ?>" placeholder="074000000">
      <p class="text-muted" style="margin:4px 0 0;">Utilisé pour la vérification d'identité en cas de mot de passe oublié.</p>
    </div>
    <div class="form-row"><label>Mot de passe *</label><input type="password" name="mot_de_passe" required minlength="6" autocomplete="new-password">
      <p class="text-muted" style="margin:4px 0 0;font-size:.82rem;"><?= htmlspecialchars(\App\Models\Utilisateur::MDP_REGLES) ?></p>
    </div>
    <?php $roleSelectionne = $old['role'] ?? ''; ?>
    <div class="form-row">
      <label>Rôle *</label>
      <select name="role" required>
        <option value="admin" <?= $roleSelectionne === 'admin' ? 'selected' : '' ?>>Administrateur / Responsable des stages</option>
        <option value="encadrant" <?= $roleSelectionne === 'encadrant' ? 'selected' : '' ?>>Encadrant</option>
        <option value="stagiaire" <?= $roleSelectionne === 'stagiaire' ? 'selected' : '' ?>>Stagiaire</option>
        <option value="direction" <?= $roleSelectionne === 'direction' ? 'selected' : '' ?>>Direction</option>
      </select>
    </div>
    <button class="btn" type="submit">Créer l'utilisateur</button>
    <a class="btn btn-outline" href="index.php?action=utilisateurs">Annuler</a>
  </form>
</div>
