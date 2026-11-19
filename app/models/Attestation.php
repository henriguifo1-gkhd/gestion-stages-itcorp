<?php

namespace App\Models;

use App\Core\Model;

class Attestation extends Model
{
    protected string $table = 'attestation';

    public function genererNumero(): string
    {
        $annee = date('Y');
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM attestation WHERE numero LIKE ?");
        $stmt->execute(["ATT-{$annee}-%"]);
        $n = (int)$stmt->fetch()['c'] + 1;
        return sprintf('ATT-%s-%04d', $annee, $n);
    }

    public function parStage(int $stageId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM attestation WHERE stage_id = ?");
        $stmt->execute([$stageId]);
        return $stmt->fetch() ?: null;
    }

    public function paginer(int $page, int $perPage, string $recherche = ''): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(a.numero LIKE :q1 OR s.nom LIKE :q2 OR s.prenom LIKE :q3)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
            $params['q3'] = "%{$recherche}%";
        }
        return $this->paginateRaw(
            "SELECT a.*, s.nom AS stagiaire_nom, s.prenom AS stagiaire_prenom",
            "FROM attestation a
             JOIN stage st ON st.id = a.stage_id
             JOIN convention c ON c.id = st.convention_id
             JOIN stagiaire s ON s.id = c.stagiaire_id",
            $wheres, $params, $page, $perPage, "a.date_generation DESC"
        );
    }
}
