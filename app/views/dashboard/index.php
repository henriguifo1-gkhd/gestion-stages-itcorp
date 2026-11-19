<?php use App\Core\Auth; ?>
<?= $csrf ?>
<div class="page-hero">
  <h1><?= icone('dashboard') ?> Bonjour <?= htmlspecialchars((string)$user['prenom']) ?></h1>
  <p>Tableau de bord · <?= htmlspecialchars(date('d/m/Y à H:i')) ?> · Connecté en tant que <?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></p>
</div>

<?php if (Auth::is('stagiaire')): ?>

  <?php if (!$monDossier): ?>
    <div class="card">
      <p>Votre compte n'est pas encore lié à un dossier stagiaire. Contactez l'administrateur / responsable des stages pour qu'il associe votre compte à votre fiche (cela se fait depuis la fiche du stagiaire, une fois votre candidature acceptée).</p>
    </div>
  <?php else: ?>
    <div class="card">
      <h2><?= icone('file-text') ?> Mon dossier</h2>
      <table>
        <tr><th style="width:200px;">Statut</th><td><span class="badge badge-<?= $monDossier['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$monDossier['statut'])) ?></span></td></tr>
        <tr><th>Établissement</th><td><?= htmlspecialchars($monDossier['etablissement']) ?></td></tr>
        <tr><th>Filière</th><td><?= htmlspecialchars($monDossier['filiere'] ?? '-') ?></td></tr>
        <tr><th>Poste</th><td><?= htmlspecialchars($monDossier['poste'] ?? '-') ?></td></tr>
      </table>
      <p style="margin-top:12px;"><a class="btn btn-sm" href="index.php?action=stagiaires/voir&id=<?= $monDossier['id'] ?>">Voir mon dossier complet</a></p>
    </div>

    <div class="card">
      <h2 class="section-title"><?= icone('stage') ?> Mes stages</h2>
      <?php if (empty($mesStages)): ?>
        <p class="empty-state">Aucun stage pour l'instant.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>Période</th><th>Statut</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($mesStages as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['date_debut']) ?> → <?= htmlspecialchars($s['date_fin']) ?></td>
              <td><span class="badge badge-<?= $s['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$s['statut'])) ?></span></td>
              <td><a href="index.php?action=stages/voir&id=<?= $s['id'] ?>">Détail (livrables, évaluations, attestation)</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>

<?php
// --- Graphiques : barres HTML/CSS (style.css, section « Graphiques du tableau de bord »).
$statutsStage = [
  'a_venir'    => ['À venir',     'var(--viz-1)', false],
  'en_cours'   => ['En cours',    'var(--viz-2)', false],
  'termine'    => ['Terminés',    'var(--viz-3)', false],
  'interrompu' => ['Interrompus', 'var(--viz-4)', true],   // jaune : texte foncé
];
$genres = ['F' => ['Femmes', 'var(--viz-f)'], 'M' => ['Hommes', 'var(--viz-m)']];

/** Un segment de barre : largeur en %, valeur écrite dedans si la place suffit, info-bulle au survol/focus. */
$segment = function (float $pct, string $couleur, string $texte, string $infobulle, bool $encreFoncee = false): string {
  return sprintf(
    '<span class="viz-seg%s" tabindex="0" style="width:%s%%;background:%s" data-tip="%s" aria-label="%s">%s</span>',
    $encreFoncee ? ' ink-dark' : '', round($pct, 2), $couleur,
    htmlspecialchars($infobulle), htmlspecialchars($infobulle),
    $pct >= 7 ? htmlspecialchars($texte) : ''
  );
};
$legende = function (array $items): string {
  $html = '<ul class="viz-legend">';
  foreach ($items as [$libelle, $couleur]) {
    $html .= '<li><span class="sw" style="background:' . $couleur . '"></span>' . htmlspecialchars($libelle) . '</li>';
  }
  return $html . '</ul>';
};
$pluriel = fn(int $n, string $mot) => $n . ' ' . $mot . ($n > 1 ? 's' : '');

// Par établissement : barres empilées des stages par statut, échelle commune.
$maxEtab = 1;
foreach ($statsEtablissement as $l) {
  $maxEtab = max($maxEtab, $l['a_venir'] + $l['en_cours'] + $l['termine'] + $l['interrompu']);
}

// Par genre : barres 100 % Femmes / Hommes pour chaque indicateur.
$indicateursGenre = [
  'candidatures' => 'Candidatures',
  'stagiaires'   => 'Stagiaires',
  'a_venir'      => 'Stages à venir',
  'en_cours'     => 'Stages en cours',
  'termine'      => 'Stages terminés',
  'interrompu'   => 'Stages interrompus',
];
?>

<div class="grid-stats">
  <div class="stat"><span class="stat-icon tone-warning"><?= icone('clock') ?></span><div><div class="val"><?= $candidaturesEnAttente ?></div><div class="lbl">Candidatures en attente</div></div></div>
  <div class="stat"><span class="stat-icon tone-info"><?= icone('stagiaire') ?></span><div><div class="val"><?= $totalStagiaires ?></div><div class="lbl">Stagiaires</div></div></div>
  <div class="stat"><span class="stat-icon tone-primary"><?= icone('loader') ?></span><div><div class="val"><?= $stats['en_cours'] ?></div><div class="lbl">Stages en cours</div></div></div>
  <div class="stat"><span class="stat-icon tone-success"><?= icone('check-circle') ?></span><div><div class="val"><?= $stats['termine'] ?></div><div class="lbl">Stages terminés</div></div></div>
</div>

<div class="viz-grid">
<div class="card">
  <h2><?= icone('building') ?> Stages par établissement</h2>
  <?php if (empty($statsEtablissement)): ?>
    <p class="empty-state">Aucun stagiaire ni candidature pour l'instant : le graphique apparaîtra dès les premières saisies.</p>
  <?php else: ?>
    <?= $legende(array_map(fn($s) => [$s[0], $s[1]], array_values($statutsStage))) ?>
    <?php foreach ($statsEtablissement as $etab => $l):
      $totalStages = $l['a_venir'] + $l['en_cours'] + $l['termine'] + $l['interrompu']; ?>
      <div class="viz-row">
        <div class="viz-label">
          <span class="nom" title="<?= htmlspecialchars((string)$etab) ?>"><?= htmlspecialchars((string)$etab) ?></span>
          <span class="sub"><?= $pluriel($l['stagiaires'], 'stagiaire') ?><?= $l['candidatures_en_attente'] ? ' · ' . $l['candidatures_en_attente'] . ' en attente' : '' ?></span>
        </div>
        <div class="viz-track">
          <?php if ($totalStages === 0): ?>
            <span class="viz-vide">Aucun stage</span>
          <?php else: foreach ($statutsStage as $cle => [$libelle, $couleur, $fonce]): if ($l[$cle] > 0): ?>
            <?= $segment($l[$cle] * 100 / $maxEtab, $couleur, (string)$l[$cle], $etab . ' - ' . $libelle . ' : ' . $l[$cle], $fonce) ?>
          <?php endif; endforeach; endif; ?>
        </div>
        <div class="viz-val"><?= $totalStages ?></div>
      </div>
    <?php endforeach; ?>
    <details class="viz-table">
      <summary>Voir les chiffres en tableau</summary>
      <div style="overflow-x:auto;">
      <table>
        <thead><tr><th>Établissement</th><th>Stagiaires</th><th>Cand. en attente</th>
          <?php foreach ($statutsStage as [$libelle]): ?><th><?= $libelle ?></th><?php endforeach; ?></tr></thead>
        <tbody id="stats-etab-tbody">
        <?php foreach ($statsEtablissement as $etab => $l): ?>
          <tr><td><?= htmlspecialchars((string)$etab) ?></td><td><?= $l['stagiaires'] ?></td><td><?= $l['candidatures_en_attente'] ?></td>
            <?php foreach (array_keys($statutsStage) as $cle): ?><td><?= $l[$cle] ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <div class="pagination" id="stats-etab-pagination"></div>
    </details>
  <?php endif; ?>
</div>

<div class="card">
  <h2><?= icone('user') ?> Répartition par genre</h2>
  <?= $legende(array_values($genres)) ?>
  <?php foreach ($indicateursGenre as $cle => $libelle):
    $total = $statsGenre['F'][$cle] + $statsGenre['M'][$cle]; ?>
    <div class="viz-row">
      <div class="viz-label"><span class="nom"><?= htmlspecialchars($libelle) ?></span></div>
      <div class="viz-track">
        <?php if ($total === 0): ?>
          <span class="viz-vide">Aucune donnée</span>
        <?php else: foreach ($genres as $sexe => [$nomGenre, $couleur]):
          $n = $statsGenre[$sexe][$cle];
          if ($n > 0):
            $pct = $n * 100 / $total; ?>
          <?= $segment($pct, $couleur, round($pct) . ' %', $libelle . ' - ' . $nomGenre . ' : ' . $n . ' (' . round($pct) . ' %)') ?>
        <?php endif; endforeach; endif; ?>
      </div>
      <div class="viz-val"><?= $total ?></div>
    </div>
  <?php endforeach; ?>
  <details class="viz-table">
    <summary>Voir les chiffres en tableau</summary>
    <div style="overflow-x:auto;">
    <table>
      <thead><tr><th>Indicateur</th><th>Femmes</th><th>Hommes</th><th>Total</th></tr></thead>
      <tbody id="stats-genre-tbody">
      <?php foreach ($indicateursGenre as $cle => $libelle): ?>
        <tr><td><?= $libelle ?></td><td><?= $statsGenre['F'][$cle] ?></td><td><?= $statsGenre['M'][$cle] ?></td><td><?= $statsGenre['F'][$cle] + $statsGenre['M'][$cle] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="pagination" id="stats-genre-pagination"></div>
  </details>
</div>
</div>
<script>
PaginationTableau('#stats-etab-tbody', '#stats-etab-pagination');
PaginationTableau('#stats-genre-tbody', '#stats-genre-pagination');
</script>

<div class="card">
  <h2><?= icone('search') ?> Recherche rapide</h2>
  <form id="filtres-dashboard" class="toolbar">
    <input type="text" name="q" placeholder="Nom, email..." value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>">
    <select name="etablissement">
      <option value="">Tous établissements</option>
      <?php foreach ($etablissements as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <table>
    <thead><tr><th>Nom</th><th>Établissement</th><th>Filière</th><th>Statut</th><th>Stage</th><th></th></tr></thead>
    <tbody id="dash-tbody"><tr><td colspan="6" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="dash-pagination"></div>
</div>

<script>
function statutRechercheRapide(r) {
  if (r.type === 'stagiaire') {
    return '<span class="badge badge-stagiaire">Stagiaire</span>';
  }
  return '<span class="badge badge-candidat">' + (r.sexe === 'F' ? 'Candidate' : 'Candidat') + '</span>';
}
DataTable({
  endpoint: 'index.php?action=dashboard/apiRechercheRapide',
  tableBody: '#dash-tbody',
  pagination: '#dash-pagination',
  filtersSelector: '#filtres-dashboard',
  colSpan: 6,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.nom_affiche)}</td>
    <td>${escapeHtml(r.etablissement)}</td>
    <td>${escapeHtml(r.filiere || '-')}</td>
    <td>${statutRechercheRapide(r)}</td>
    <td>${r.stage_statut ? badge(r.stage_statut) : '<span class="text-muted">Aucun</span>'}</td>
    <td>${r.type === 'stagiaire' ? lienIcone('index.php?action=stagiaires/voir&id=' + r.id, 'eye', 'Voir la fiche') : '<span class="text-muted">-</span>'}</td>
  </tr>`
});
</script>

<?php endif; ?>
