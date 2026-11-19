<h1><?= icone('utilisateurs') ?> Modifier l'utilisateur</h1>
<div class="card" style="max-width:520px;">
  <form method="post" action="index.php?action=utilisateurs/editer">
    <?= $csrf ?>
    <input type="hidden" name="id" value="<?= $utilisateur['id'] ?>">
    <div class="form-grid">
      <div class="form-row"><label>Nom *</label><input name="nom" value="<?= htmlspecialchars($old['nom'] ?? $utilisateur['nom']) ?>" required></div>
      <div class="form-row"><label>Prénom *</label><input name="prenom" value="<?= htmlspecialchars($old['prenom'] ?? $utilisateur['prenom']) ?>" required></div>
    </div>
    <div class="form-row"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? $utilisateur['email']) ?>" required></div>
    <div class="form-row">
      <label>Téléphone</label>
      <input type="tel" name="telephone" value="<?= htmlspecialchars($old['telephone'] ?? ($utilisateur['telephone'] ?? '')) ?>" placeholder="074000000">
      <p class="text-muted" style="margin:4px 0 0;">Utilisé pour la vérification d'identité en cas de mot de passe oublié.</p>
    </div>
    <?php $roleSelectionne = $old['role'] ?? $utilisateur['role']; ?>
    <div class="form-row">
      <label>Rôle *</label>
      <select name="role" required>
        <option value="admin" <?= $roleSelectionne === 'admin' ? 'selected' : '' ?>>Administrateur / Responsable des stages</option>
        <option value="encadrant" <?= $roleSelectionne === 'encadrant' ? 'selected' : '' ?>>Encadrant</option>
        <option value="stagiaire" <?= $roleSelectionne === 'stagiaire' ? 'selected' : '' ?>>Stagiaire</option>
        <option value="direction" <?= $roleSelectionne === 'direction' ? 'selected' : '' ?>>Direction</option>
      </select>
    </div>
    <p class="text-muted">Le mot de passe ne se modifie pas ici : l'utilisateur peut le changer depuis « Mon compte », ou le réinitialiser via « Mot de passe oublié » sur la page de connexion.</p>
    <button class="btn" type="submit">Enregistrer</button>
    <a class="btn btn-outline" href="index.php?action=utilisateurs">Annuler</a>
  </form>
</div>
