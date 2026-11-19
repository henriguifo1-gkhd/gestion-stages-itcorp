<h1><?= icone('bell') ?> Notifications</h1>

<div class="toolbar">
  <form method="post" action="index.php?action=notifications/toutLu" style="margin:0;">
    <?= $csrf ?>
    <button class="btn btn-outline btn-sm" type="submit">Tout marquer comme lu</button>
  </form>
</div>

<div class="card card-table">
  <table>
    <thead><tr><th>Date</th><th>Notification</th><th></th></tr></thead>
    <tbody id="notif-tbody"><tr><td colspan="3" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="notif-pagination"></div>
</div>

<script>
DataTable({
  endpoint: 'index.php?action=notifications/apiListe',
  tableBody: '#notif-tbody',
  pagination: '#notif-pagination',
  colSpan: 3,
  renderRow: (r) => `<tr>
    <td style="white-space:nowrap;">${escapeHtml(r.created_at)}</td>
    <td>${r.lu == 0 ? '<span class="badge badge-en_attente">Nouvelle</span> <strong>' + escapeHtml(r.message) + '</strong>' : '<span class="text-muted">' + escapeHtml(r.message) + '</span>'}</td>
    <td>${lienIcone('index.php?action=notifications/ouvrir&id=' + r.id, 'eye', 'Ouvrir la notification')}</td>
  </tr>`
});
</script>
