<h1><?= icone('candidature') ?> Nouvelle candidature</h1>
<div class="card" style="max-width:640px;">
  <form method="post" action="index.php?action=candidatures/creer">
    <?= $csrf ?>
    <div class="form-grid">
      <div class="form-row"><label>Nom complet *</label><input name="nom_complet" value="<?= htmlspecialchars($old['nom_complet'] ?? '') ?>" required></div>
      <div class="form-row">
        <label>Sexe *</label>
        <select name="sexe" required>
          <option value="M" <?= ($old['sexe'] ?? 'M') === 'M' ? 'selected' : '' ?>>Homme</option>
          <option value="F" <?= ($old['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Femme</option>
        </select>
      </div>
      <div class="form-row"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required></div>
      <div class="form-row"><label>Téléphone</label><input name="telephone" value="<?= htmlspecialchars($old['telephone'] ?? '') ?>"></div>
      <div class="form-row"><label>Établissement *</label><input name="etablissement" value="<?= htmlspecialchars($old['etablissement'] ?? '') ?>" required></div>
      <div class="form-row"><label>Filière</label><input name="filiere" value="<?= htmlspecialchars($old['filiere'] ?? '') ?>"></div>
      <div class="form-row"><label>Niveau d'étude</label><input name="niveau_etude" value="<?= htmlspecialchars($old['niveau_etude'] ?? '') ?>"></div>
      <div class="form-row"><label>Poste souhaité *</label><input name="poste" value="<?= htmlspecialchars($old['poste'] ?? '') ?>" required></div>
      <div class="form-row"><label>Date de candidature</label><input type="date" name="date_candidature" value="<?= htmlspecialchars($old['date_candidature'] ?? date('Y-m-d')) ?>"></div>
    </div>
    <button class="btn" type="submit">Enregistrer la candidature</button>
    <a class="btn btn-outline" href="index.php?action=candidatures">Annuler</a>
  </form>
</div>
