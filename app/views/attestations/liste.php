<h1><?= icone('attestation') ?> Attestations</h1>

<form id="filtres-att" class="toolbar">
  <input type="text" name="q" placeholder="Numéro, nom du stagiaire...">
</form>

<div class="card card-table">
  <table>
    <thead><tr><th>Numéro</th><th>Stagiaire</th><th>Date de génération</th><th></th></tr></thead>
    <tbody id="att-tbody"><tr><td colspan="4" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="att-pagination"></div>
</div>

<script>
DataTable({
  endpoint: 'index.php?action=attestations/apiListe',
  tableBody: '#att-tbody',
  pagination: '#att-pagination',
  filtersSelector: '#filtres-att',
  colSpan: 4,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.numero)}</td>
    <td>${escapeHtml(r.stagiaire_nom)} ${escapeHtml(r.stagiaire_prenom)}</td>
    <td>${escapeHtml(r.date_generation)}</td>
    <td><div class="actions-cell">${lienIcone('index.php?action=attestations/telecharger&mode=ouvrir&id=' + r.id, 'eye', 'Ouvrir l\'attestation', ' target="_blank" rel="noopener"')}${lienIcone('index.php?action=attestations/telecharger&id=' + r.id, 'download', 'Télécharger l\'attestation')}</div></td>
  </tr>`
});
</script>
