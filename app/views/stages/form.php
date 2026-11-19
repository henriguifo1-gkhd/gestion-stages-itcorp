<h1><?= icone('stage') ?> Créer un stage</h1>
<?php if (!$convention || $convention['statut'] !== 'validee'): ?>
  <div class="flash flash-error">Impossible de créer le stage : la convention doit être validée (Règle 3 du cahier des charges).</div>
  <a class="btn" href="index.php?action=stagiaires">Retour</a>
<?php elseif ($stagiaireADejaUnStageActif): ?>
  <div class="flash flash-error">Impossible de créer le stage : ce stagiaire a déjà un stage en cours ou à venir. Un nouveau stage ne peut démarrer qu'après la fin (ou l'interruption) du précédent.</div>
  <a class="btn" href="index.php?action=stagiaires/voir&id=<?= $convention['stagiaire_id'] ?>">Retour à la fiche du stagiaire</a>
<?php else: ?>
<div class="card" style="max-width:560px;">
  <p class="text-muted">Convention : <strong><?= htmlspecialchars($convention['reference']) ?></strong> - <?= htmlspecialchars($convention['sujet']) ?></p>
  <form method="post" action="index.php?action=stages/creer">
    <?= $csrf ?>
    <input type="hidden" name="convention_id" value="<?= $convention['id'] ?>">
    <div class="form-row"><label>Date de début *</label><input type="date" name="date_debut" value="<?= htmlspecialchars($convention['date_debut']) ?>" required></div>
    <div class="form-row"><label>Date de fin *</label><input type="date" name="date_fin" value="<?= htmlspecialchars($convention['date_fin']) ?>" required></div>
    <button class="btn" type="submit">Créer le stage</button>
    <a class="btn btn-outline" href="index.php?action=stagiaires/voir&id=<?= $convention['stagiaire_id'] ?>">Annuler</a>
  </form>
</div>
<?php endif; ?>
