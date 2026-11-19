<h1><?= icone('stagiaire') ?> Stagiaires</h1>

<form id="filtres-stag" class="toolbar">
  <input type="text" name="q" placeholder="Nom, prénom, email...">
  <select name="statut">
    <option value="">Tous statuts</option>
    <option value="actif">Actif</option>
    <option value="desiste">Désisté</option>
    <option value="archive">Archivé</option>
  </select>
  <select name="etablissement">
    <option value="">Tous établissements</option>
    <?php foreach ($etablissements as $e): ?>
      <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="card card-table">
  <table>
    <thead><tr><th>Nom</th><th>Établissement</th><th>Filière</th><th>Niveau</th><th>Statut</th><th></th></tr></thead>
    <tbody id="stag-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="stag-pagination"></div>
</div>

<script>
DataTable({
  endpoint: 'index.php?action=stagiaires/apiListe',
  tableBody: '#stag-tbody',
  pagination: '#stag-pagination',
  filtersSelector: '#filtres-stag',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.nom)} ${escapeHtml(r.prenom)}</td>
    <td>${escapeHtml(r.etablissement)}</td>
    <td>${escapeHtml(r.filiere || '-')}</td>
    <td>${escapeHtml(r.niveau_etude || '-')}</td>
    <td>${badge(r.statut)}</td>
    <td>${lienIcone('index.php?action=stagiaires/voir&id=' + r.id, 'eye', 'Voir la fiche')}</td>
  </tr>`
});
</script>
