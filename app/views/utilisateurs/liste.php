<h1><?= icone('utilisateurs') ?> Utilisateurs</h1>

<div class="toolbar">
  <form id="filtres-user" class="toolbar" style="margin:0;">
    <input type="text" name="q" placeholder="Nom, email...">
    <select name="role">
      <option value="">Tous rôles</option>
      <option value="admin">Administrateur</option>
      <option value="encadrant">Encadrant</option>
      <option value="stagiaire">Stagiaire</option>
      <option value="direction">Direction</option>
    </select>
  </form>
  <a class="btn" href="index.php?action=utilisateurs/creerForm"><?= icone('plus') ?> Nouvel utilisateur</a>
</div>

<div class="card card-table">
  <table>
    <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody id="user-tbody"><tr><td colspan="5" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="user-pagination"></div>
</div>

<script>
const MOI_ID = <?= (int)$moiId ?>;
function actionsUser(r) {
  const csrf = document.querySelector('input[name=csrf_token]').value;
  const modifier = lienIcone('index.php?action=utilisateurs/editerForm&id=' + r.id, 'pencil', 'Modifier l\'utilisateur');
  // Un administrateur ne peut pas désactiver son propre compte (sinon plus personne
  // pour le réactiver) : on masque simplement le bouton sur sa propre ligne.
  if (r.actif == 1 && r.id == MOI_ID) {
    return `${modifier} <span class="text-muted" style="font-size:.8rem;">Votre compte</span>`;
  }
  const action = r.actif == 1 ? 'desactiver' : 'activer';
  const label = r.actif == 1 ? 'Désactiver' : 'Activer';
  const cls = r.actif == 1 ? 'btn-danger' : 'btn-success';
  return `${modifier}
    <form method="post" action="index.php?action=utilisateurs/${action}" style="display:inline;">
    <input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${r.id}">
    <button class="btn ${cls} btn-sm">${label}</button></form>`;
}
DataTable({
  endpoint: 'index.php?action=utilisateurs/apiListe',
  tableBody: '#user-tbody',
  pagination: '#user-pagination',
  filtersSelector: '#filtres-user',
  colSpan: 5,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.prenom)} ${escapeHtml(r.nom)}</td>
    <td>${escapeHtml(r.email)}</td>
    <td>${escapeHtml(r.role)}</td>
    <td>${r.actif == 1 ? '<span class="badge badge-actif">Actif</span>' : '<span class="badge badge-desiste">Inactif</span>'}</td>
    <td><div class="actions-cell">${actionsUser(r)}</div></td>
  </tr>`
});
</script>
<?= $csrf ?>
