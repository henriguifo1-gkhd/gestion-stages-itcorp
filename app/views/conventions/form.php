<h1><?= icone('convention') ?> Nouvelle convention</h1>
<div class="card" style="max-width:640px;">
  <form method="post" action="index.php?action=conventions/creer">
    <?= $csrf ?>
    <div class="form-row">
      <label>Stagiaire *</label>
      <select name="stagiaire_id" required>
        <option value="">-- Sélectionner --</option>
        <?php $preselection = (int)($old['stagiaire_id'] ?? $stagiaireIdPreselectionne ?? 0); ?>
        <?php foreach ($stagiaires as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $preselection === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nom'] . ' ' . $s['prenom'] . ' - ' . $s['etablissement']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-grid">
      <div class="form-row"><label>Référence</label><input name="reference" value="<?= htmlspecialchars($old['reference'] ?? $reference) ?>" required></div>
      <div class="form-row"><label>Type</label><input name="type" value="<?= htmlspecialchars($old['type'] ?? '') ?>" placeholder="Stage académique / professionnel" required></div>
      <div class="form-row" style="grid-column:1/-1;"><label>Sujet *</label><input name="sujet" value="<?= htmlspecialchars($old['sujet'] ?? '') ?>" required></div>
      <div class="form-row"><label>Date début *</label><input type="date" name="date_debut" value="<?= htmlspecialchars($old['date_debut'] ?? '') ?>" required></div>
      <div class="form-row"><label>Date fin *</label><input type="date" name="date_fin" value="<?= htmlspecialchars($old['date_fin'] ?? '') ?>" required></div>
    </div>
    <button class="btn" type="submit">Créer la convention</button>
    <a class="btn btn-outline" href="index.php?action=conventions">Annuler</a>
  </form>
</div>
