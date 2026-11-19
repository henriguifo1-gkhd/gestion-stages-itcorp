<?php

namespace App\Models;

use App\Core\Model;

class Affectation extends Model
{
    protected string $table = 'affectation';

    public function historiquePourStage(int $stageId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.nom, u.prenom FROM affectation a JOIN utilisateur u ON u.id = a.encadrant_id
             WHERE a.stage_id = ? ORDER BY a.date_debut DESC"
        );
        $stmt->execute([$stageId]);
        return $stmt->fetchAll();
    }

    /**
     * Change l'encadrant d'un stage en conservant l'historique (Règle 4).
     * Clôture l'affectation en cours (date_fin = aujourd'hui) et en ouvre une nouvelle.
     */
    public function reaffecter(int $stageId, int $nouvelEncadrantId): int
    {
        $stmt = $this->db->prepare("UPDATE affectation SET date_fin = CURDATE() WHERE stage_id = ? AND date_fin IS NULL");
        $stmt->execute([$stageId]);

        return $this->insert([
            'stage_id' => $stageId,
            'encadrant_id' => $nouvelEncadrantId,
            'date_debut' => date('Y-m-d'),
            'date_fin' => null,
        ]);
    }
}
