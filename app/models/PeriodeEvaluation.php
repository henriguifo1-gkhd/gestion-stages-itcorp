<?php

namespace App\Models;

use App\Core\Model;

/**
 * Table de référence periode_evaluation (code, libelle, ordre).
 * Depuis le nouveau schéma, la période d'une évaluation n'est plus une saisie
 * libre : elle doit correspondre à l'un des codes définis ici (03_referentiel.sql).
 */
class PeriodeEvaluation extends Model
{
    protected string $table = 'periode_evaluation';
    protected string $primaryKey = 'code';

    public function toutes(): array
    {
        return $this->db->query("SELECT * FROM periode_evaluation ORDER BY ordre ASC")->fetchAll();
    }

    public function existe(string $code): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM periode_evaluation WHERE code = ?");
        $stmt->execute([$code]);
        return (int)$stmt->fetch()['c'] > 0;
    }
}
