<h1><?= icone('stagiaire') ?> Modifier la fiche stagiaire</h1>
<div class="card" style="max-width:640px;">
  <form method="post" action="index.php?action=stagiaires/editer">
    <?= $csrf ?>
    <input type="hidden" name="id" value="<?= $stagiaire['id'] ?>">
    <div class="form-grid">
      <div class="form-row"><label>Nom</label><input value="<?= htmlspecialchars($stagiaire['nom']) ?>" disabled></div>
      <div class="form-row"><label>Prénom</label><input value="<?= htmlspecialchars($stagiaire['prenom']) ?>" disabled></div>
      <div class="form-row">
        <label>Sexe</label>
        <select name="sexe">
          <option value="M" <?= ($stagiaire['sexe'] ?? 'M') === 'M' ? 'selected' : '' ?>>Homme</option>
          <option value="F" <?= ($stagiaire['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
      </div>
      <div class="form-row"><label>Téléphone</label><input name="telephone" value="<?= htmlspecialchars($stagiaire['telephone'] ?? '') ?>"></div>
      <div class="form-row"><label>Établissement</label><input name="etablissement" value="<?= htmlspecialchars($stagiaire['etablissement']) ?>"></div>
      <div class="form-row"><label>Filière</label><input name="filiere" value="<?= htmlspecialchars($stagiaire['filiere'] ?? '') ?>"></div>
      <div class="form-row"><label>Niveau d'étude</label><input name="niveau_etude" value="<?= htmlspecialchars($stagiaire['niveau_etude'] ?? '') ?>"></div>
    </div>
    <button class="btn" type="submit">Enregistrer</button>
    <a class="btn btn-outline" href="index.php?action=stagiaires/voir&id=<?= $stagiaire['id'] ?>">Annuler</a>
  </form>
</div>
