<?php use App\Core\Auth; ?>
<?= $csrf ?>
<h1><?= icone('stage') ?> Stage - <?= htmlspecialchars($stage['stagiaire_nom'] . ' ' . $stage['stagiaire_prenom']) ?>
  <span class="badge badge-<?= $stage['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$stage['statut'])) ?></span>
</h1>

<?php if ($incoherenceConvention): ?>
  <div class="flash flash-error">⚠ Incohérence : la convention associée (<?= htmlspecialchars($stage['convention_reference']) ?>) arrive à échéance alors que le stage n'est pas clôturé. Une prolongation de convention doit être enregistrée.</div>
<?php endif; ?>

<div class="card">
  <table>
    <tr><th style="width:220px;">Stagiaire</th><td><a href="index.php?action=stagiaires/voir&id=<?= $stage['stagiaire_id'] ?>"><?= htmlspecialchars($stage['stagiaire_nom'] . ' ' . $stage['stagiaire_prenom']) ?></a> (<?= htmlspecialchars($stage['etablissement']) ?>)</td></tr>
    <tr><th>Convention</th><td><?= htmlspecialchars($stage['convention_reference']) ?> <span class="badge badge-<?= $stage['convention_statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$stage['convention_statut'])) ?></span></td></tr>
    <tr><th>Période</th><td><?= htmlspecialchars($stage['date_debut']) ?> → <?= htmlspecialchars($stage['date_fin']) ?></td></tr>
    <tr><th>Encadrant actuel</th><td><?= $stage['encadrant_nom'] ? htmlspecialchars($stage['encadrant_prenom'] . ' ' . $stage['encadrant_nom']) : '<span class="text-muted">Aucun - le stage ne peut pas démarrer (cas limite)</span>' ?></td></tr>
  </table>

  <?php if (Auth::is('admin')): ?>
  <div class="mini-form" style="margin-top:16px;">
    <form method="post" action="index.php?action=stages/affecter" class="mini-form">
      <?= $csrf ?><input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">
      <div class="form-row">
        <label>Affecter un encadrant</label>
        <select name="encadrant_id" required>
          <option value="">-- Choisir --</option>
          <?php foreach ($encadrants as $e): ?>
            <option value="<?= $e['id'] ?>" <?= (int)$stage['encadrant_actuel_id'] === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm" type="submit">Affecter</button>
    </form>

    <?php if ($stage['statut'] === 'a_venir'): ?>
      <form method="post" action="index.php?action=stages/demarrer"><?= $csrf ?><input type="hidden" name="id" value="<?= $stage['id'] ?>"><button class="btn btn-success btn-sm">Démarrer le stage</button></form>
    <?php elseif ($stage['statut'] === 'en_cours'): ?>
      <form method="post" action="index.php?action=stages/cloturer"><?= $csrf ?><input type="hidden" name="id" value="<?= $stage['id'] ?>"><button class="btn btn-success btn-sm" onclick="return confirmerPuisSoumettre(event, 'Clôturer ce stage ?')">Clôturer</button></form>
      <form method="post" action="index.php?action=stages/interrompre" onsubmit="return confirmerPuisSoumettre(event, 'Interrompre ce stage avant son terme ?')">
        <?= $csrf ?><input type="hidden" name="id" value="<?= $stage['id'] ?>">
        <input type="text" name="motif" placeholder="Motif (optionnel)" style="width:160px;">
        <button class="btn btn-danger btn-sm">Interrompre</button>
      </form>
    <?php elseif ($stage['statut'] === 'interrompu' && $stage['stagiaire_statut'] !== 'archive'): ?>
      <form method="post" action="index.php?action=stagiaires/supprimer" onsubmit="return confirmerPuisSoumettre(event, 'Archiver ce stagiaire ? Son stage etant interrompu, son dossier sera archive et non supprime : tout l historique reste consultable.')">
        <?= $csrf ?><input type="hidden" name="id" value="<?= $stage['stagiaire_id'] ?>">
        <button class="btn btn-outline btn-sm">Archiver ce stagiaire</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($affectations)): ?>
<div class="card">
  <h2 class="section-title"><?= icone('history') ?> Historique des encadrants</h2>
  <table>
    <thead><tr><th>Encadrant</th><th>Début</th><th>Fin</th></tr></thead>
    <tbody>
    <?php foreach ($affectations as $a): ?>
      <tr><td><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></td><td><?= htmlspecialchars($a['date_debut']) ?></td><td><?= htmlspecialchars($a['date_fin'] ?? 'en cours') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2 class="section-title"><?= icone('livrable') ?> Livrables</h2>
  <table id="tbl-livrables">
    <thead><tr><th>Titre</th><th>Échéance</th><th>Dépôt</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody id="livr-tbody"><tr><td colspan="5" class="empty-state">Chargement...</td></tr></tbody>
  </table>
  <div class="pagination" id="livr-pagination"></div>

  <?php if ($peutGerer): ?>
  <details style="margin-top:12px;"><summary style="cursor:pointer;color:var(--bleu);font-weight:600;">+ Ajouter une échéance de livrable</summary>
    <form method="post" action="index.php?action=livrables/ajouterEcheance" class="mini-form" style="margin-top:10px;">
      <?= $csrf ?><input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">
      <div class="form-row"><label>Titre</label><input name="titre" required></div>
      <div class="form-row"><label>Échéance</label><input type="date" name="date_echeance" required></div>
      <button class="btn btn-sm" type="submit">Ajouter</button>
    </form>
  </details>
  <?php endif; ?>
  <?php if (Auth::is('stagiaire')): ?>
    <p class="text-muted" style="margin-top:10px;">Pour déposer un livrable, utilisez le bouton « Déposer » sur sa ligne dans le tableau ci-dessus, une fois son échéance visible.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title"><?= icone('evaluation') ?> Évaluations</h2>
  <?php if (!$evaluationsVisibles): ?>
    <p class="empty-state">Vos évaluations seront consultables une fois votre stage terminé.</p>
  <?php elseif (empty($evaluations)): ?>
    <p class="empty-state">Aucune évaluation saisie.</p>
  <?php else: ?>
    <?php foreach ($evaluations as $e): ?>
      <table style="margin-bottom:10px;">
        <thead><tr><th>Période</th><th>Note /20</th><th>Appréciation</th></tr></thead>
        <tbody>
          <tr><td><?= htmlspecialchars($e['periode_libelle']) ?></td><td><?= htmlspecialchars(number_format((float)$e['note_globale'], 2, ',', '')) ?></td><td><?= htmlspecialchars($e['appreciation_generale'] ?? '-') ?></td></tr>
        </tbody>
      </table>
      <?php if (!empty($e['criteres'])): ?>
        <table style="margin-bottom:16px;">
          <thead><tr><th>Critère</th><th>Note</th><th>Commentaire</th></tr></thead>
          <tbody>
          <?php foreach ($e['criteres'] as $c): ?>
            <tr><td><?= htmlspecialchars($c['libelle']) ?></td><td><?= $c['note'] !== null ? htmlspecialchars((string)$c['note']) . '/' . htmlspecialchars((string)$c['bareme']) : '-' ?></td><td><?= htmlspecialchars($c['commentaire'] ?? '-') ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
      <?php
        // Modification : admin, ou l'encadrant actuel s'il est l'auteur de l'évaluation
        // (l'évaluation reste rattachée à son auteur, même après un changement d'encadrant).
        $peutModifierEval = $peutGerer && !empty($criteresEvaluation)
            && (Auth::is('admin') || (int)$e['encadrant_id'] === Auth::id());
      ?>
      <?php if ($peutModifierEval):
        $notesActuelles = [];
        foreach ($e['criteres'] as $c) { $notesActuelles[(int)$c['critere_id']] = $c; }
      ?>
        <details style="margin:-6px 0 18px;"><summary style="cursor:pointer;color:var(--bleu);font-weight:600;">Modifier cette évaluation</summary>
          <form method="post" action="index.php?action=evaluations/modifier" class="form-evaluation" style="margin-top:10px;">
            <?= $csrf ?><input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
            <table style="margin-bottom:10px;">
              <thead><tr><th>Critère</th><th style="width:110px;">Note</th><th>Commentaire</th></tr></thead>
              <tbody>
              <?php foreach ($criteresEvaluation as $c):
                $actuel = $notesActuelles[(int)$c['id']] ?? null;
                $valeur = $actuel && $actuel['note'] !== null ? rtrim(rtrim(number_format((float)$actuel['note'], 2, ',', ''), '0'), ',') : '';
              ?>
                <tr>
                  <td><?= htmlspecialchars($c['libelle']) ?></td>
                  <td><input type="text" inputmode="decimal" autocomplete="off" pattern="\d+([.,]\d{1,2})?" title="Nombre entre 0 et <?= htmlspecialchars((string)(float)$c['bareme']) ?> (ex. : 14 ou 14,5)" name="note_critere_<?= $c['id'] ?>" value="<?= htmlspecialchars($valeur) ?>" class="note-critere" data-bareme="<?= htmlspecialchars((string)$c['bareme']) ?>" style="width:90px;" required> /<?= htmlspecialchars((string)$c['bareme']) ?></td>
                  <td><input type="text" name="commentaire_critere_<?= $c['id'] ?>" value="<?= htmlspecialchars((string)($actuel['commentaire'] ?? '')) ?>" style="width:100%;"></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <p class="form-row"><strong>Note globale (calculée automatiquement) : <span class="note-globale-calculee"><?= htmlspecialchars(number_format((float)$e['note_globale'], 2, '.', '')) ?></span> / 20</strong></p>
            <div class="form-row"><label>Appréciation générale</label><textarea name="appreciation_generale" rows="3"><?= htmlspecialchars((string)($e['appreciation_generale'] ?? '')) ?></textarea></div>
            <button class="btn btn-sm" type="submit">Enregistrer les modifications</button>
          </form>
        </details>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($peutGerer): ?>
    <?php if (!$stage['encadrant_actuel_id']): ?>
      <p class="text-muted" style="margin-top:12px;">Aucun encadrant n'est affecté à ce stage pour l'instant : une évaluation ne peut pas être saisie tant qu'un encadrant n'a pas été affecté<?= Auth::is('admin') ? ' (voir « Affecter un encadrant » ci-dessus)' : '' ?>.</p>
    <?php else: ?>
      <details style="margin-top:12px;"><summary style="cursor:pointer;color:var(--bleu);font-weight:600;">+ Saisir une évaluation</summary>
        <form method="post" action="index.php?action=evaluations/creer" class="form-evaluation" style="margin-top:10px;">
          <?= $csrf ?><input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">
          <div class="form-grid">
            <div class="form-row">
              <label>Période</label>
              <select name="periode_code" required>
                <option value="">-- Choisir --</option>
                <?php foreach ($periodesEvaluation as $p): ?>
                  <option value="<?= htmlspecialchars($p['code']) ?>"><?= htmlspecialchars($p['libelle']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <?php if (!empty($criteresEvaluation)): ?>
            <div class="form-row"><label>Grille de notation par critère *</label></div>
            <table style="margin-bottom:10px;">
              <thead><tr><th>Critère</th><th style="width:110px;">Note</th><th>Commentaire</th></tr></thead>
              <tbody>
              <?php foreach ($criteresEvaluation as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['libelle']) ?></td>
                  <td><input type="text" inputmode="decimal" autocomplete="off" pattern="\d+([.,]\d{1,2})?" title="Nombre entre 0 et <?= htmlspecialchars((string)(float)$c['bareme']) ?> (ex. : 14 ou 14,5)" placeholder="0 à <?= htmlspecialchars((string)(float)$c['bareme']) ?>" name="note_critere_<?= $c['id'] ?>" class="note-critere" data-bareme="<?= htmlspecialchars((string)$c['bareme']) ?>" style="width:90px;" required> /<?= htmlspecialchars((string)$c['bareme']) ?></td>
                  <td><input type="text" name="commentaire_critere_<?= $c['id'] ?>" style="width:100%;"></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <p class="form-row"><strong>Note globale (calculée automatiquement) : <span class="note-globale-calculee">-</span> / 20</strong></p>
          <?php endif; ?>
          <div class="form-row"><label>Appréciation générale</label><textarea name="appreciation_generale" rows="3"></textarea></div>
          <button class="btn btn-sm" type="submit">Enregistrer l'évaluation</button>
        </form>
      </details>
      <p class="text-muted">Une évaluation existante pour la même période ne peut pas être dupliquée (Règle 5) : modifiez-la au lieu d'en créer une nouvelle.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title"><?= icone('attestation') ?> Attestation de fin de stage</h2>
  <?php if ($attestation): ?>
    <p>Attestation <strong><?= htmlspecialchars($attestation['numero']) ?></strong> générée le <?= htmlspecialchars($attestation['date_generation']) ?>.
      <?php $fichierAttestation = $attestation['fichier_path'] ? __DIR__ . '/../../../public/' . $attestation['fichier_path'] : null; ?>
      <?php if ($fichierAttestation && is_file($fichierAttestation)): ?>
        <?php if (\App\Core\Controller::estOuvrable($fichierAttestation)): ?>
        <a class="btn btn-outline btn-sm" href="index.php?action=attestations/telecharger&id=<?= $attestation['id'] ?>&mode=ouvrir" target="_blank" rel="noopener"><?= icone('eye') ?> Ouvrir</a>
        <?php endif; ?>
        <a class="btn btn-sm" href="index.php?action=attestations/telecharger&id=<?= $attestation['id'] ?>"><?= icone('download') ?> Télécharger l'attestation</a>
      <?php endif; ?>
    </p>
  <?php elseif ($stage['statut'] === 'termine' && Auth::is('admin', 'direction')): ?>
    <form method="post" action="index.php?action=attestations/generer">
      <?= $csrf ?><input type="hidden" name="id" value="<?= $stage['id'] ?>">
      <button class="btn" type="submit">Générer l'attestation</button>
    </form>
  <?php elseif ($stage['statut'] === 'interrompu'): ?>
    <p class="text-muted">Ce stage a été interrompu avant son terme : aucune attestation ne peut être générée.</p>
  <?php else: ?>
    <p class="text-muted">L'attestation ne pourra être générée qu'une fois le stage clôturé (terminé).</p>
  <?php endif; ?>
</div>

<script>
// Calcul en direct de la note globale, pour chaque formulaire d'évaluation (saisie et modification).
document.querySelectorAll('.form-evaluation').forEach(function (form) {
  const champs = form.querySelectorAll('.note-critere');
  const sortie = form.querySelector('.note-globale-calculee');
  if (!sortie) return;
  function calculer() {
    let obtenu = 0, bareme = 0, complet = true;
    champs.forEach(ch => {
      const valeur = ch.value.trim().replace(',', '.');
      const max = parseFloat(ch.dataset.bareme);
      const note = /^\d+(\.\d{1,2})?$/.test(valeur) ? parseFloat(valeur) : NaN;
      const valide = !isNaN(note) && note <= max;
      ch.setCustomValidity(valeur === '' || valide ? '' : 'La note doit être comprise entre 0 et ' + max + '.');
      if (!valide) { complet = false; return; }
      obtenu += note; bareme += max;
    });
    sortie.textContent = complet && bareme > 0 ? (Math.round(obtenu / bareme * 2000) / 100).toFixed(2) : '-';
  }
  champs.forEach(ch => ch.addEventListener('input', calculer));
});
DataTable({
  endpoint: 'index.php?action=livrables/apiListeParStage',
  tableBody: '#livr-tbody',
  pagination: '#livr-pagination',
  extraParams: () => ({ stage_id: <?= (int)$stage['id'] ?> }),
  colSpan: 5,
  renderRow: (r) => `<tr>
    <td>${escapeHtml(r.titre)}</td>
    <td>${escapeHtml(r.date_echeance)}</td>
    <td>${r.date_depot ? escapeHtml(r.date_depot) + (r.hors_delai == 1 ? ' <span class="hors-delai">(hors délai)</span>' : '') : '<span class="text-muted">Non déposé</span>'}${r.date_depot && !r.fichier_existe ? ' <span class="text-muted" style="font-size:.78rem;">(aucun fichier disponible)</span>' : ''}</td>
    <td>${badge(r.statut_validation)}</td>
    <td style="white-space:nowrap;">${boutonTelecharger(r)} ${!r.date_depot ? formulaireDepot(r.id) : (r.statut_validation === 'en_attente' ? actionsLivrable(r.id) : (r.statut_validation === 'rejete' ? formulaireDepot(r.id, true) : ''))} ${boutonSupprimerLivrable(r.id)}</td>
  </tr>`
});
function boutonTelecharger(r) {
  // Visible uniquement si le livrable a été déposé et que son fichier existe réellement.
  if (!r.date_depot || !r.fichier_existe) return '';
  const url = 'index.php?action=livrables/telecharger&id=' + r.id;
  return (r.fichier_ouvrable ? '<a class="btn btn-outline btn-sm" href="' + url + '&mode=ouvrir" target="_blank" rel="noopener">' + iconeJs('eye') + ' Ouvrir</a> ' : '')
    + '<a class="btn btn-sm" href="' + url + '">' + iconeJs('download') + ' Télécharger</a>';
}
function formulaireDepot(id, redepot = false) {
  <?php if (Auth::is('stagiaire')): ?>
  const csrf = document.querySelector('input[name=csrf_token]').value;
  return `<form method="post" action="index.php?action=livrables/deposer" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;">
    <input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${id}">
    <input type="file" name="fichier" required style="font-size:.78rem;max-width:140px;">
    <button class="btn btn-sm" type="submit">${redepot ? 'Redéposer' : 'Déposer'}</button></form>`;
  <?php else: ?>
  return redepot ? '<span class="text-muted">En attente d\'une nouvelle version</span>' : '<span class="text-muted">En attente du dépôt par le stagiaire</span>';
  <?php endif; ?>
}
function actionsLivrable(id) {
  <?php if ($peutGerer): ?>
  const csrf = document.querySelector('input[name=csrf_token]').value;
  return `<form method="post" action="index.php?action=livrables/valider" style="display:inline;"><input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${id}"><button class="btn btn-success btn-sm">Valider</button></form>
    <form method="post" action="index.php?action=livrables/rejeter" style="display:inline;"><input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${id}"><button class="btn btn-danger btn-sm">Rejeter</button></form>`;
  <?php else: ?>
  return '';
  <?php endif; ?>
}
function boutonSupprimerLivrable(id) {
  <?php if ($peutGerer): ?>
  const csrf = document.querySelector('input[name=csrf_token]').value;
  return `<form method="post" action="index.php?action=livrables/archiver" style="display:inline;" onsubmit="return confirmerPuisSoumettre(event, 'Supprimer ce livrable ? Il sera archive : retire des listes mais conserve (fichier compris) et restaurable depuis le filtre Archives.')"><input type="hidden" name="csrf_token" value="${csrf}"><input type="hidden" name="id" value="${id}"><button class="btn btn-outline btn-sm">Supprimer</button></form>`;
  <?php else: ?>
  return '';
  <?php endif; ?>
}
</script>
