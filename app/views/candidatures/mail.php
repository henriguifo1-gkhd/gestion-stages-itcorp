<?php
use App\Models\Candidature;

$c = $candidature;
$sujet = $old['sujet'] ?? $modeleMail['sujet'];
$corps = $old['corps'] ?? $modeleMail['corps'];
$statuts = ['en_attente' => 'En attente', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée', 'desistee' => 'Désistée'];
$g = \App\Core\Genre::mots($c['sexe'] ?? 'M');
?>
<h1><?= icone('mail') ?> Informer <?= $g['le_candidat'] ?> par mail</h1>

<div class="card" style="max-width:760px;">
  <p>
    <strong><?= htmlspecialchars($c['nom_complet']) ?></strong>
    &lt;<?= htmlspecialchars($c['email']) ?>&gt;<br>
    <span class="text-muted">
      Poste : <?= htmlspecialchars($c['poste']) ?> · <?= htmlspecialchars($c['etablissement']) ?> ·
      Statut : <span class="badge badge-<?= htmlspecialchars($c['statut']) ?>"><?= $statuts[$c['statut']] ?? htmlspecialchars($c['statut']) ?></span>
      <?php if (!empty($c['dernier_mail_le'])): ?>
        · Dernier mail envoyé le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($c['dernier_mail_le']))) ?>
      <?php endif; ?>
    </span>
  </p>

  <form method="get" action="index.php" class="form-row">
    <input type="hidden" name="action" value="candidatures/mailForm">
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <label>Modèle de message</label>
    <select name="modele" onchange="this.form.submit()">
      <?php foreach (Candidature::MODELES_MAIL as $code => $libelle): ?>
        <option value="<?= $code ?>" <?= $modeleMail['modele'] === $code ? 'selected' : '' ?>><?= $libelle ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <form method="post" action="index.php?action=candidatures/envoyerMail">
    <?= $csrf ?>
    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
    <div class="form-row"><label>Destinataire</label><input value="<?= htmlspecialchars($c['email']) ?>" readonly></div>
    <div class="form-row"><label>Objet *</label><input name="sujet" value="<?= htmlspecialchars($sujet) ?>" required></div>
    <div class="form-row"><label>Message *</label><textarea name="corps" rows="14" required><?= htmlspecialchars($corps) ?></textarea></div>
    <button class="btn" type="submit"><?= icone('mail') ?> Envoyer le mail</button>
    <?php if (!empty($c['stagiaire_id'])): ?>
      <a class="btn btn-outline" href="index.php?action=stagiaires/voir&id=<?= (int)$c['stagiaire_id'] ?>">Plus tard (voir la fiche stagiaire)</a>
    <?php else: ?>
      <a class="btn btn-outline" href="index.php?action=candidatures">Plus tard</a>
    <?php endif; ?>
  </form>
</div>
