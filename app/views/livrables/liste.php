<h1><?= icone('livrable') ?> Livrables</h1>

<form id="filtres-livr" class="toolbar">
  <select name="statut">
    <option value="">Tous statuts</option>
    <option value="en_attente">En attente</option>
    <option value="valide">Validé</option>
    <option value="rejete">Rejeté</option>
    <option value="archive">Archivés (supprimés)</option>
  </select>
</form>

<?= $csrf ?>
<div class="card card-table">
  <table>
    <thead><tr><th>Stagiaire</th><th>Titre</th><th>Échéance</th><th>Dépôt</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody id="livr-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="livr-pagination"></div>
</div>

<script>
function boutonTelecharger(r) {
  // Visible uniquement si le livrable a été déposé et que son fichier existe réellement.
  if (!r.date_depot || !r.fichier_existe) return '';
  const url = 'index.php?action=livrables/telecharger&id=' + r.id;
  return (r.fichier_ouvrable ? '<a class="btn btn-outline btn-sm" href="' + url + '&mode=ouvrir" target="_blank" rel="noopener">' + iconeJs('eye') + ' Ouvrir</a> ' : '')
    + '<a class="btn btn-outline btn-sm" href="' + url + '">' + iconeJs('download') + ' Télécharger</a>';
}
function formArchivage(id, action, libelle, confirmation) {
  <?php if (\App\Core\Auth::is('admin', 'encadrant')): ?>
  const csrf = document.querySelector('input[name=csrf_token]').value;
  return `<form method="post" action="index.php?action=livrables/${action}" style="display:inline;"${confirmation ? ` onsubmit="return confirmerPuisSoumettre(event, '${confirmation}')"` : ''}><input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${id}"><input type="hidden" name="retour" value="liste"><button class="btn btn-outline btn-sm">${libelle}</button></form>`;
  <?php else: ?>
  return '';
  <?php endif; ?>
}
function boutonSupprimerLivrable(id) {
  return formArchivage(id, 'archiver', 'Supprimer', "Supprimer ce livrable ? Il sera archive : retire de la liste mais conserve (fichier compris) et restaurable depuis le filtre Archives.");
}
function boutonRestaurerLivrable(id) {
  return formArchivage(id, 'restaurer', 'Restaurer', '');
}
DataTable({
  endpoint: 'index.php?action=livrables/apiListe',
  tableBody: '#livr-tbody',
  pagination: '#livr-pagination',
  filtersSelector: '#filtres-livr',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.stagiaire_nom)} ${escapeHtml(r.stagiaire_prenom)}</td>
    <td>${escapeHtml(r.titre)}${r.archive == 1 ? ' <span class="text-muted" style="font-size:.78rem;">(archivé)</span>' : ''}</td>
    <td>${escapeHtml(r.date_echeance)}</td>
    <td>${r.date_depot ? escapeHtml(r.date_depot) + (r.hors_delai == 1 ? ' <span class="hors-delai">(hors délai)</span>' : '') : '<span class="text-muted">Non déposé</span>'}${r.date_depot && !r.fichier_existe ? ' <span class="text-muted" style="font-size:.78rem;">(aucun fichier disponible)</span>' : ''}</td>
    <td>${badge(r.statut_validation)}</td>
    <td><div class="actions-cell">${boutonTelecharger(r)} ${r.archive == 1 ? boutonRestaurerLivrable(r.id) : (r.date_depot ? boutonSupprimerLivrable(r.id) : '')}</div></td>
  </tr>`
});
</script>
