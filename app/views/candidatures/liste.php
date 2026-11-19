<h1><?= icone('candidature') ?> Candidatures</h1>

<div class="toolbar">
  <form id="filtres-cand" class="toolbar" style="margin:0;">
    <input type="text" name="q" placeholder="Nom, email, établissement, poste...">
    <select name="statut">
      <option value="">Tous statuts</option>
      <option value="en_attente">En attente</option>
      <option value="acceptee">Acceptée</option>
      <option value="refusee">Refusée</option>
      <option value="desistee">Désistée</option>
    </select>
  </form>
  <?php if (\App\Core\Auth::is('admin')): ?>
    <a class="btn" href="index.php?action=candidatures/creerForm"><?= icone('plus') ?> Nouvelle candidature</a>
  <?php endif; ?>
</div>

<div class="card card-table">
  <table>
    <thead><tr><th>Candidat</th><th>Poste</th><th>Établissement</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody id="cand-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="cand-pagination"></div>
</div>

<script>
const estAdmin = <?= \App\Core\Auth::is('admin') ? 'true' : 'false' ?>;
function actionsCandidature(r) {
  if (!estAdmin) return '<span class="text-muted">-</span>';
  const mail = lienIcone('index.php?action=candidatures/mailForm&id=' + r.id, 'mail', 'Envoyer un mail au candidat');
  const csrf = document.querySelector('input[name=csrf_token]').value;
  if (r.statut === 'acceptee') {
    // Cas limite : désistement après acceptation, avant le démarrage du stage (dossier conservé).
    return mail + `
    <form method="post" action="index.php?action=candidatures/desister" style="display:inline;">
      <input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${r.id}">
      <button class="btn btn-outline btn-sm" onclick="return confirmerPuisSoumettre(event, 'Enregistrer le désistement de ce candidat ? Le dossier est conservé et la fiche stagiaire passe au statut désisté.')">Désistement</button>
    </form>`;
  }
  if (r.statut !== 'en_attente') return mail;
  return mail + `
    <form method="post" action="index.php?action=candidatures/accepter" style="display:inline;">
      <input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${r.id}">
      <button class="btn btn-success btn-sm" onclick="return confirmerPuisSoumettre(event, 'Accepter cette candidature ? Une fiche stagiaire sera créée.')">${iconeJs('check')} Accepter</button>
    </form>
    <form method="post" action="index.php?action=candidatures/refuser" style="display:inline;">
      <input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${r.id}">
      <button class="btn btn-danger btn-sm" onclick="return confirmerPuisSoumettre(event, 'Refuser cette candidature ?')">${iconeJs('x')} Refuser</button>
    </form>`;
}
DataTable({
  endpoint: 'index.php?action=candidatures/apiListe',
  tableBody: '#cand-tbody',
  pagination: '#cand-pagination',
  filtersSelector: '#filtres-cand',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.nom_complet)}<br><span class="text-muted">${escapeHtml(r.email)}</span></td>
    <td>${escapeHtml(r.poste)}</td>
    <td>${escapeHtml(r.etablissement)}</td>
    <td>${escapeHtml(r.date_candidature)}</td>
    <td>${badge(r.statut)}${r.dernier_mail_le ? '<br><span class="text-muted">Mail envoyé le ' + escapeHtml(r.dernier_mail_le.substring(0, 16)) + '</span>' : ''}</td>
    <td><div class="actions-cell">${actionsCandidature(r)}</div></td>
  </tr>`
});
</script>
<?= $csrf ?>
