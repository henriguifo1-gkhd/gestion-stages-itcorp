<?php use App\Core\Auth; ?>
<h1><?= icone('stage') ?> Stages</h1>

<form id="filtres-stage" class="toolbar">
  <input type="text" name="q" placeholder="Nom du stagiaire...">
  <select name="statut">
    <option value="">Tous statuts</option>
    <option value="a_venir">À venir</option>
    <option value="en_cours">En cours</option>
    <option value="termine">Terminé</option>
    <option value="interrompu">Interrompu</option>
  </select>
  <?php if (Auth::is('admin', 'direction')): ?>
  <select name="encadrant_id">
    <option value="">Tous encadrants</option>
    <?php foreach ($encadrants as $e): ?>
      <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
</form>

<div class="card card-table">
  <table>
    <thead><tr><th>Stagiaire</th><th>Établissement</th><th>Période</th><th>Encadrant</th><th>Statut</th><th></th></tr></thead>
    <tbody id="stage-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="stage-pagination"></div>
</div>

<script>
DataTable({
  endpoint: 'index.php?action=stages/apiListe',
  tableBody: '#stage-tbody',
  pagination: '#stage-pagination',
  filtersSelector: '#filtres-stage',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.stagiaire_nom)} ${escapeHtml(r.stagiaire_prenom)}</td>
    <td>${escapeHtml(r.etablissement)}</td>
    <td>${escapeHtml(r.date_debut)} → ${escapeHtml(r.date_fin)}</td>
    <td>${r.encadrant_nom ? escapeHtml(r.encadrant_prenom) + ' ' + escapeHtml(r.encadrant_nom) : '<span class="text-muted">Non affecté</span>'}</td>
    <td>${badge(r.statut)}</td>
    <td>${lienIcone('index.php?action=stages/voir&id=' + r.id, 'eye', 'Voir le détail du stage')}</td>
  </tr>`
});
</script>
