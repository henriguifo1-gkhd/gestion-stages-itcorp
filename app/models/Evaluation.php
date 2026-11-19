<?php

namespace App\Models;

use App\Core\Model;

class Evaluation extends Model
{
    protected string $table = 'evaluation';

    /**
     * Note globale calculée par le système : total des notes obtenues rapporté
     * au total des barèmes, ramené sur 20 (arrondi à 2 décimales).
     * @param array $notes liste de ['note' => float, 'bareme' => float]
     */
    public static function calculerNoteGlobale(array $notes): float
    {
        $obtenu = array_sum(array_column($notes, 'note'));
        $bareme = array_sum(array_column($notes, 'bareme'));
        return $bareme > 0 ? round($obtenu / $bareme * 20, 2) : 0.0;
    }

    /** Règle 5 : empêcher deux évaluations identiques pour le même stage + période. */
    public function existeDeja(int $stageId, string $periodeCode, ?int $ignorerId = null): ?array
    {
        $sql = "SELECT * FROM evaluation WHERE stage_id = ? AND periode_code = ?";
        $params = [$stageId, $periodeCode];
        if ($ignorerId) {
            $sql .= " AND id != ?";
            $params[] = $ignorerId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public function paginerParStage(int $stageId, int $page, int $perPage): array
    {
        return $this->paginateRaw(
            "SELECT e.*, u.nom AS encadrant_nom, u.prenom AS encadrant_prenom",
            "FROM evaluation e JOIN utilisateur u ON u.id = e.encadrant_id WHERE e.stage_id = :sid",
            [], ['sid' => $stageId], $page, $perPage, "e.periode_code ASC"
        );
    }

    public function toutesParStage(int $stageId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM evaluation WHERE stage_id = ? ORDER BY periode_code");
        $stmt->execute([$stageId]);
        return $stmt->fetchAll();
    }

    /** Notes par critère pour une évaluation donnée (grille normalisée). */
    public function criteres(int $evaluationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ec.critere_id, ec.note, ec.commentaire, cr.libelle, cr.bareme
             FROM evaluation_critere ec JOIN critere cr ON cr.id = ec.critere_id
             WHERE ec.evaluation_id = ? ORDER BY cr.id"
        );
        $stmt->execute([$evaluationId]);
        return $stmt->fetchAll();
    }
}
