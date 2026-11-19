<h1><?= icone('convention') ?> Conventions</h1>

<div class="toolbar">
  <form id="filtres-conv" class="toolbar" style="margin:0;">
    <input type="text" name="q" placeholder="Référence, sujet, stagiaire...">
    <select name="statut">
      <option value="">Tous statuts</option>
      <option value="en_attente">En attente</option>
      <option value="validee">Validée</option>
      <option value="rejetee">Rejetée</option>
      <option value="terminee">Terminée</option>
    </select>
  </form>
  <?php if (\App\Core\Auth::is('admin')): ?>
    <a class="btn" href="index.php?action=conventions/creerForm"><?= icone('plus') ?> Nouvelle convention</a>
  <?php endif; ?>
</div>

<div class="card card-table">
  <table>
    <thead><tr><th>Référence</th><th>Stagiaire</th><th>Sujet</th><th>Période</th><th>Statut</th><th></th></tr></thead>
    <tbody id="conv-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="conv-pagination"></div>
</div>

<script>
DataTable({
  endpoint: 'index.php?action=conventions/apiListe',
  tableBody: '#conv-tbody',
  pagination: '#conv-pagination',
  filtersSelector: '#filtres-conv',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.reference)}</td>
    <td>${escapeHtml(r.stagiaire_nom)} ${escapeHtml(r.stagiaire_prenom)}</td>
    <td>${escapeHtml(r.sujet)}</td>
    <td>${escapeHtml(r.date_debut)} → ${escapeHtml(r.date_fin)}</td>
    <td>${badge(r.statut)}</td>
    <td><div class="actions-cell">${lienIcone('index.php?action=stagiaires/voir&id=' + r.stagiaire_id, 'user', 'Voir le stagiaire')}${lienIcone('index.php?action=conventions/telechargerPdf&mode=ouvrir&id=' + r.id, 'eye', 'Ouvrir la convention (PDF)', ' target="_blank" rel="noopener"')}${r.document_existe ? (r.document_ouvrable ? lienIcone('index.php?action=conventions/telechargerDocument&mode=ouvrir&id=' + r.id, 'external', 'Ouvrir la convention signée (scan)', ' target="_blank" rel="noopener"') : '') + lienIcone('index.php?action=conventions/telechargerDocument&id=' + r.id, 'download', 'Télécharger la convention signée') : ''}</div></td>
  </tr>`
});
</script>
