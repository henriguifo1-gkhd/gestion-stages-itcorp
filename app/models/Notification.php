<?php

namespace App\Models;

use App\Core\Model;

/**
 * Notifications internes. Un dépôt de livrable est signalé à l'encadrant actuel du stage
 * et à tous les administrateurs actifs.
 */
class Notification extends Model
{
    protected string $table = 'notification';

    public function creer(int $utilisateurId, string $type, string $message, ?string $lien = null): int
    {
        return $this->insert([
            'utilisateur_id' => $utilisateurId,
            'type' => $type,
            'message' => mb_substr($message, 0, 255, 'UTF-8'),
            'lien' => $lien,
        ]);
    }

    /**
     * Dépôt d'un livrable : notifie l'encadrant actuel du stage (s'il y en a un) et
     * tous les administrateurs actifs. Un même compte n'est jamais notifié deux fois.
     */
    public function notifierDepotLivrable(int $stageId, string $stagiaire, string $titre, bool $horsDelai, ?int $encadrantId): int
    {
        $destinataires = $this->db->query("SELECT id FROM utilisateur WHERE role = 'admin' AND actif = 1")->fetchAll(\PDO::FETCH_COLUMN);
        if ($encadrantId) {
            $destinataires[] = $encadrantId;
        }
        $message = $stagiaire . ' a déposé le livrable « ' . $titre . ' »' . ($horsDelai ? ' (hors délai).' : '.');
        $lien = 'index.php?action=stages/voir&id=' . $stageId;
        $n = 0;
        foreach (array_unique(array_map('intval', $destinataires)) as $uid) {
            $this->creer($uid, 'depot_livrable', $message, $lien);
            $n++;
        }
        return $n;
    }

    /** Affectation d'un encadrant à un stage : l'encadrant est informé qu'un stagiaire lui est confié. */
    public function notifierAffectation(int $encadrantId, int $stageId, string $stagiaire, string $etablissement, string $sexe = 'M'): int
    {
        $g = \App\Core\Genre::mots($sexe);
        $message = $g['Un_stagiaire'] . ' vous a été ' . $g['affecte'] . ' : ' . $stagiaire . ($etablissement !== '' ? ' (' . $etablissement . ').' : '.');
        return $this->creer($encadrantId, 'affectation_stagiaire', $message, 'index.php?action=stages/voir&id=' . $stageId);
    }

    public function compterNonLues(int $utilisateurId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM notification WHERE utilisateur_id = ? AND lu = 0");
        $stmt->execute([$utilisateurId]);
        return (int)$stmt->fetch()['c'];
    }

    public function paginerPourUtilisateur(int $utilisateurId, int $page, int $perPage): array
    {
        return $this->paginateRaw(
            "SELECT n.*",
            "FROM notification n WHERE n.utilisateur_id = :uid",
            [], ['uid' => $utilisateurId], $page, $perPage, "n.created_at DESC, n.id DESC"
        );
    }

    /** Marque une notification comme lue, uniquement si elle appartient bien à cet utilisateur. */
    public function marquerLue(int $id, int $utilisateurId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE notification SET lu = 1, date_lecture = NOW() WHERE id = ? AND utilisateur_id = ? AND lu = 0"
        );
        return $stmt->execute([$id, $utilisateurId]);
    }

    public function toutMarquerLu(int $utilisateurId): void
    {
        $stmt = $this->db->prepare("UPDATE notification SET lu = 1, date_lecture = NOW() WHERE utilisateur_id = ? AND lu = 0");
        $stmt->execute([$utilisateurId]);
    }
}
