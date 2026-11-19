<?php

namespace App\Models;

use App\Core\Model;

/**
 * Table de référence critere (libelle, bareme, actif) : grille de notation
 * normalisée qui remplace l'ancien champ libre evaluation.grille_critere.
 */
class Critere extends Model
{
    protected string $table = 'critere';

    public function actifs(): array
    {
        return $this->db->query("SELECT * FROM critere WHERE actif = 1 ORDER BY id ASC")->fetchAll();
    }
}
