<?php use App\Core\Auth; ?>
<?= $csrf ?>
<h1><?= icone('stagiaire') ?> Fiche stagiaire - <?= htmlspecialchars($stagiaire['nom'] . ' ' . $stagiaire['prenom']) ?> <?= /* badge */ '' ?>
  <span class="badge badge-<?= $stagiaire['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$stagiaire['statut'])) ?></span>
</h1>

<div class="card">
  <h2><?= icone('user') ?> Informations personnelles</h2>
  <table>
    <tr><th style="width:200px;">Sexe</th><td><?= ($stagiaire['sexe'] ?? 'M') === 'F' ? 'Femme' : 'Homme' ?></td></tr>
    <tr><th>Email</th><td><?= htmlspecialchars($stagiaire['email']) ?></td></tr>
    <tr><th>Téléphone</th><td><?= htmlspecialchars($stagiaire['telephone'] ?? '-') ?></td></tr>
    <tr><th>Établissement</th><td><?= htmlspecialchars($stagiaire['etablissement']) ?></td></tr>
    <tr><th>Filière</th><td><?= htmlspecialchars($stagiaire['filiere'] ?? '-') ?></td></tr>
    <tr><th>Niveau d'étude</th><td><?= htmlspecialchars($stagiaire['niveau_etude'] ?? '-') ?></td></tr>
    <tr><th>Poste (candidature d'origine)</th><td><?= htmlspecialchars($stagiaire['poste'] ?? '-') ?></td></tr>
  </table>
  <?php if (Auth::is('admin')): ?>
    <div class="actions-row" style="margin-top:12px;">
      <a class="btn btn-outline btn-sm" href="index.php?action=stagiaires/editerForm&id=<?= $stagiaire['id'] ?>">Modifier</a>
      <?php if ($stagiaire['statut'] !== 'archive'): ?>
      <form method="post" action="index.php?action=stagiaires/supprimer" style="display:inline;">
        <?= $csrf ?><input type="hidden" name="id" value="<?= $stagiaire['id'] ?>">
        <button class="btn btn-danger btn-sm" onclick="return confirmerPuisSoumettre(event, 'Supprimer ce stagiaire ? Si un historique existe, il sera archivé au lieu d\'être supprimé.')">Supprimer</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (Auth::is('admin')): ?>
<div class="card">
  <h2 class="section-title"><?= icone('link') ?> Compte utilisateur lié (accès à l'espace personnel du stagiaire)</h2>
  <?php if (!empty($stagiaire['utilisateur_id'])): ?>
    <p>Compte lié : <strong><?= htmlspecialchars($stagiaire['compte_prenom'] . ' ' . $stagiaire['compte_nom']) ?></strong> (<?= htmlspecialchars($stagiaire['compte_email']) ?>). Ce stagiaire peut se connecter avec ce compte pour consulter son dossier depuis son tableau de bord.</p>
    <form method="post" action="index.php?action=stagiaires/delierCompte" style="margin-top:8px;">
      <?= $csrf ?><input type="hidden" name="id" value="<?= $stagiaire['id'] ?>">
      <button class="btn btn-outline btn-sm" onclick="return confirmerPuisSoumettre(event, 'Délier ce compte ? Le stagiaire ne pourra plus consulter son dossier tant qu\'un nouveau compte n\'est pas lié.')">Délier le compte</button>
    </form>
  <?php else: ?>
    <p class="text-muted">Aucun compte utilisateur n'est encore lié à cette fiche : le stagiaire ne peut pas consulter son dossier. Créez d'abord son compte (rôle "stagiaire") dans <a href="index.php?action=utilisateurs">Utilisateurs</a>, puis liez-le ici.</p>
    <?php if (!empty($comptesDisponibles)): ?>
    <form method="post" action="index.php?action=stagiaires/lierCompte" class="mini-form" style="margin-top:8px;">
      <?= $csrf ?><input type="hidden" name="id" value="<?= $stagiaire['id'] ?>">
      <div class="form-row">
        <label>Compte à lier</label>
        <select name="utilisateur_id" required>
          <option value="">-- Choisir --</option>
          <?php foreach ($comptesDisponibles as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['prenom'] . ' ' . $c['nom'] . ' (' . $c['email'] . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-sm" type="submit">Lier ce compte</button>
    </form>
    <?php else: ?>
      <p class="text-muted">Aucun compte "stagiaire" disponible pour l'instant (tous déjà liés, ou aucun créé).</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2 class="section-title"><?= icone('convention') ?> Conventions</h2>
  <?php if (empty($conventions)): ?>
    <p class="empty-state">Aucune convention.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Référence</th><th>Type</th><th>Sujet</th><th>Période</th><th>Statut</th><th>Document</th><?php if (Auth::is('admin')): ?><th>Actions</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($conventions as $c): ?>
        <tr>
          <td><?= htmlspecialchars($c['reference']) ?></td>
          <td><?= htmlspecialchars($c['type']) ?></td>
          <td><?= htmlspecialchars($c['sujet']) ?></td>
          <td><?= htmlspecialchars($c['date_debut']) ?> → <?= htmlspecialchars($c['date_fin']) ?></td>
          <td><span class="badge badge-<?= $c['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$c['statut'])) ?></span></td>
          <td>
            <?php
              // Un encadrant n'accède qu'aux conventions des stages qu'il encadre actuellement.
              $pdfAccessible = !Auth::is('encadrant') || (bool)array_filter($stages,
                  fn($s) => (int)$s['convention_id'] === (int)$c['id'] && (int)$s['encadrant_actuel_id'] === Auth::id());
            ?>
            <?php if ($pdfAccessible): ?>
              <span title="Convention générée avec les informations actuelles">Convention :
                <a href="index.php?action=conventions/telechargerPdf&id=<?= $c['id'] ?>&mode=ouvrir" target="_blank" rel="noopener"><?= icone('eye') ?> Ouvrir</a> ·
                <a href="index.php?action=conventions/telechargerPdf&id=<?= $c['id'] ?>"><?= icone('download') ?> Télécharger</a></span><br>
            <?php endif; ?>
            <?php if (!empty($c['document_existe']) && $pdfAccessible): ?>
              <span>Document signé :
                <?php if (!empty($c['document_ouvrable'])): ?>
                <a href="index.php?action=conventions/telechargerDocument&id=<?= $c['id'] ?>&mode=ouvrir" target="_blank" rel="noopener"><?= icone('eye') ?> Ouvrir</a> ·
                <?php endif; ?>
                <a href="index.php?action=conventions/telechargerDocument&id=<?= $c['id'] ?>"><?= icone('download') ?> Télécharger</a></span>
            <?php endif; ?>
            <?php if (Auth::is('admin')): ?>
              <form method="post" action="index.php?action=conventions/televerserDocument" enctype="multipart/form-data" style="margin-top:6px;">
                <?= $csrf ?>
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" style="max-width:150px;font-size:.72rem;display:block;margin-bottom:4px;">
                <button class="btn btn-outline btn-sm" type="submit" style="padding:3px 8px;font-size:.72rem;">
                  <?= !empty($c['document_path']) ? 'Remplacer le scan' : 'Téléverser le scan' ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
          <?php if (Auth::is('admin')): ?>
          <td>
            <?php if ($c['statut'] === 'en_attente'): ?>
              <form method="post" action="index.php?action=conventions/valider" style="display:inline;"><?= $csrf ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-success btn-sm">Valider</button></form>
              <form method="post" action="index.php?action=conventions/rejeter" style="display:inline;"><?= $csrf ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-danger btn-sm">Rejeter</button></form>
            <?php elseif ($c['statut'] === 'validee'): ?>
              <?php if ($aUnStageActif): ?>
                <span class="text-muted">Stage en cours/à venir déjà actif</span>
              <?php else: ?>
                <a class="btn btn-sm" href="index.php?action=stages/creerForm&convention_id=<?= $c['id'] ?>">Créer le stage</a>
              <?php endif; ?>
              <form method="post" action="index.php?action=conventions/prolonger" style="margin-top:6px;" onsubmit="return confirmerPuisSoumettre(event, 'Prolonger cette convention jusqu\'à la date choisie ?')">
                <?= $csrf ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
                <input type="date" name="nouvelle_date_fin" min="<?= htmlspecialchars(date('Y-m-d', strtotime($c['date_fin'] . ' +1 day'))) ?>" required style="font-size:.78rem;max-width:140px;">
                <button class="btn btn-outline btn-sm" type="submit" style="padding:3px 8px;font-size:.72rem;">Prolonger</button>
              </form>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (Auth::is('admin') && $stagiaire['statut'] === 'actif'): ?>
  <details style="margin-top:14px;">
    <summary style="cursor:pointer;color:var(--bleu);font-weight:600;">+ Nouvelle convention</summary>
    <form method="post" action="index.php?action=conventions/creer" class="mini-form" style="margin-top:12px;">
      <?= $csrf ?>
      <input type="hidden" name="stagiaire_id" value="<?= $stagiaire['id'] ?>">
      <div class="form-row"><label>Référence</label><input name="reference" value="<?= (new \App\Models\Convention())->genererReference() ?>" required></div>
      <div class="form-row"><label>Type</label><input name="type" placeholder="Stage académique" required></div>
      <div class="form-row"><label>Sujet</label><input name="sujet" required></div>
      <div class="form-row"><label>Début</label><input type="date" name="date_debut" required></div>
      <div class="form-row"><label>Fin</label><input type="date" name="date_fin" required></div>
      <button class="btn btn-sm" type="submit">Créer la convention</button>
    </form>
  </details>
  <?php endif; ?>
</div>

<div class="card">
  <h2 class="section-title"><?= icone('stage') ?> Stages</h2>
  <?php if (empty($stages)): ?>
    <p class="empty-state">Aucun stage.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Période</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($stages as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['date_debut']) ?> → <?= htmlspecialchars($s['date_fin']) ?></td>
          <td><span class="badge badge-<?= $s['statut'] ?>"><?= htmlspecialchars(libelle_statut((string)$s['statut'])) ?></span></td>
          <td>
            <?php if (!\App\Core\Auth::is('encadrant') || (int)$s['encadrant_actuel_id'] === \App\Core\Auth::id()): ?>
              <a href="index.php?action=stages/voir&id=<?= $s['id'] ?>">Détail du stage</a>
            <?php else: ?>
              <span class="text-muted">Réservé à l'encadrant du stage</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (!empty($historique)): ?>
<div class="card">
  <h2 class="section-title"><?= icone('history') ?> Historique / traçabilité</h2>
  <table>
    <thead><tr><th>Date</th><th>Évènement</th><th>Description</th></tr></thead>
    <tbody>
    <?php foreach ($historique as $h): ?>
      <tr><td><?= htmlspecialchars($h['created_at']) ?></td><td><?= htmlspecialchars($h['evenement']) ?></td><td><?= htmlspecialchars($h['description']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
