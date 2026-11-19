<?php

namespace App\Models;

use App\Core\Model;

class Livrable extends Model
{
    protected string $table = 'livrable';

    /**
     * Ajoute à chaque ligne l'indicateur fichier_existe : vrai seulement si un fichier a été
     * déposé ET se trouve réellement sur le disque. Le bouton « Ouvrir » s'appuie dessus.
     */
    public static function marquerFichiers(array $resultat): array
    {
        $racine = __DIR__ . '/../../public/';
        foreach ($resultat['data'] as &$ligne) {
            $ligne['fichier_existe'] = !empty($ligne['fichier_path']) && is_file($racine . $ligne['fichier_path']);
            // PDF, image ou texte : affichable directement dans le navigateur (bouton « Ouvrir »).
            $ligne['fichier_ouvrable'] = $ligne['fichier_existe'] && \App\Core\Controller::estOuvrable($racine . $ligne['fichier_path']);
        }
        unset($ligne);
        return $resultat;
    }

    public function paginerParStage(int $stageId, int $page, int $perPage): array
    {
        return self::marquerFichiers($this->paginateRaw(
            "SELECT *",
            "FROM livrable WHERE stage_id = :sid AND archive = 0",
            [], ['sid' => $stageId], $page, $perPage, "date_echeance ASC"
        ));
    }

    /**
     * @param int|null $encadrantId si renseigné, ne retourne que les livrables des stages dont
     *                              cet encadrant est l'encadrant actuel (affectation ouverte).
     */
    public function paginer(int $page, int $perPage, string $statut = '', ?int $encadrantId = null): array
    {
        // Par défaut, seuls les livrables actifs ; le filtre « archive » affiche les archivés.
        $wheres = ['l.archive = ' . ($statut === 'archive' ? '1' : '0')];
        $params = [];
        if ($encadrantId !== null) {
            $wheres[] = "EXISTS (SELECT 1 FROM affectation aff WHERE aff.stage_id = st.id AND aff.date_fin IS NULL AND aff.encadrant_id = :enc)";
            $params['enc'] = $encadrantId;
        }
        if ($statut !== '' && $statut !== 'archive') {
            $wheres[] = "l.statut_validation = :statut";
            $params['statut'] = $statut;
        }
        return self::marquerFichiers($this->paginateRaw(
            "SELECT l.*, s.nom AS stagiaire_nom, s.prenom AS stagiaire_prenom",
            "FROM livrable l
             JOIN stage st ON st.id = l.stage_id
             JOIN convention c ON c.id = st.convention_id
             JOIN stagiaire s ON s.id = c.stagiaire_id",
            $wheres, $params, $page, $perPage, "l.date_echeance ASC"
        ));
    }

    /** Nombre de livrables déposés et en attente de décision (filtrable sur l'encadrant actuel). */
    public function compterDeposesEnAttente(?int $encadrantId = null): int
    {
        $sql = "SELECT COUNT(*) c FROM livrable l WHERE l.statut_validation = 'en_attente' AND l.date_depot IS NOT NULL AND l.archive = 0";
        $params = [];
        if ($encadrantId !== null) {
            $sql .= " AND EXISTS (SELECT 1 FROM affectation aff WHERE aff.stage_id = l.stage_id AND aff.date_fin IS NULL AND aff.encadrant_id = ?)";
            $params[] = $encadrantId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['c'];
    }

    /**
     * Cas limite : dépôt après échéance -> accepté mais marqué "hors délai".
     * hors_delai est désormais une colonne générée (STORED) par la base : elle
     * ne doit jamais figurer dans un INSERT/UPDATE, elle se recalcule seule.
     */
    public function deposer(int $id, ?string $fichierPath): bool
    {
        // Un redépôt après rejet repart « en attente » : la décision précédente (valideur, date)
        // est effacée, sinon la contrainte chk_livrable_validation refuse la mise à jour.
        return $this->update($id, [
            'date_depot' => date('Y-m-d H:i:s'),
            'fichier_path' => $fichierPath,
            'statut_validation' => 'en_attente',
            'valide_par' => null,
            'date_validation' => null,
        ]);
    }

    /** « Supprime » un livrable en l'archivant : rien n'est effacé, ni la ligne ni le fichier. */
    public function archiver(int $id, int $utilisateurId): bool
    {
        return $this->update($id, [
            'archive' => 1,
            'date_archivage' => date('Y-m-d H:i:s'),
            'archive_par' => $utilisateurId,
        ]);
    }

    public function restaurer(int $id): bool
    {
        return $this->update($id, ['archive' => 0, 'date_archivage' => null, 'archive_par' => null]);
    }
}
