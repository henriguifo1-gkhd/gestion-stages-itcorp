<?php

namespace App\Models;

use App\Core\Model;

class Stage extends Model
{
    protected string $table = 'stage';

    public function findAvecDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT st.*, s.id AS stagiaire_id, s.nom AS stagiaire_nom, s.prenom AS stagiaire_prenom,
                    s.etablissement, s.statut AS stagiaire_statut, s.sexe AS stagiaire_sexe,
                    c.reference AS convention_reference, c.statut AS convention_statut, c.date_fin AS convention_date_fin,
                    aff.encadrant_id AS encadrant_actuel_id, u.nom AS encadrant_nom, u.prenom AS encadrant_prenom
             FROM stage st
             JOIN convention c ON c.id = st.convention_id
             JOIN stagiaire s ON s.id = c.stagiaire_id
             LEFT JOIN affectation aff ON aff.stage_id = st.id AND aff.date_fin IS NULL
             LEFT JOIN utilisateur u ON u.id = aff.encadrant_id
             WHERE st.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Cas limite : convention arrivée à échéance avant la fin réelle du stage -> incohérence signalée. */
    public function conventionEnIncoherence(array $stage): bool
    {
        if ($stage['statut'] !== 'en_cours') {
            return false;
        }
        return strtotime($stage['convention_date_fin']) < time();
    }

    /**
     * Règle métier : un stagiaire ne peut pas avoir deux stages en parallèle.
     * Un nouveau stage ne peut être créé que si tous les stages précédents de ce
     * stagiaire sont arrivés à leur terme (statut "termine") ou ont été interrompus
     * (statut "interrompu") - "a_venir" et "en_cours" comptent comme actifs.
     */
    public function aUnStageActif(int $stagiaireId, ?int $ignorerStageId = null): bool
    {
        $sql = "SELECT COUNT(*) c FROM stage st JOIN convention c ON c.id = st.convention_id
                WHERE c.stagiaire_id = ? AND st.statut IN ('a_venir','en_cours')";
        $params = [$stagiaireId];
        if ($ignorerStageId) {
            $sql .= " AND st.id != ?";
            $params[] = $ignorerStageId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'] > 0;
    }

    public function paginer(int $page, int $perPage, string $recherche = '', string $statut = '', string $etablissement = '', ?int $encadrantId = null): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(s.nom LIKE :q1 OR s.prenom LIKE :q2)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
        }
        if ($statut !== '') {
            $wheres[] = "st.statut = :statut";
            $params['statut'] = $statut;
        }
        if ($etablissement !== '') {
            $wheres[] = "s.etablissement = :etab";
            $params['etab'] = $etablissement;
        }
        if ($encadrantId) {
            $wheres[] = "aff.encadrant_id = :enc";
            $params['enc'] = $encadrantId;
        }
        return $this->paginateRaw(
            "SELECT st.*, s.nom AS stagiaire_nom, s.prenom AS stagiaire_prenom, s.etablissement,
                    u.nom AS encadrant_nom, u.prenom AS encadrant_prenom",
            "FROM stage st
             JOIN convention c ON c.id = st.convention_id
             JOIN stagiaire s ON s.id = c.stagiaire_id
             LEFT JOIN affectation aff ON aff.stage_id = st.id AND aff.date_fin IS NULL
             LEFT JOIN utilisateur u ON u.id = aff.encadrant_id",
            $wheres, $params, $page, $perPage, "st.date_debut DESC"
        );
    }

    public function compterParStatut(): array
    {
        $stmt = $this->db->query("SELECT statut, COUNT(*) c FROM stage GROUP BY statut");
        $out = ['a_venir' => 0, 'en_cours' => 0, 'termine' => 0, 'interrompu' => 0];
        foreach ($stmt->fetchAll() as $r) {
            $out[$r['statut']] = (int)$r['c'];
        }
        return $out;
    }
}
