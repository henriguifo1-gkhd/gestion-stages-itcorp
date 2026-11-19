<?php

namespace App\Models;

use App\Core\Model;

class Stagiaire extends Model
{
    protected string $table = 'stagiaire';

    /** Dernière candidature acceptée d'un stagiaire (poste, date), au cas où plusieurs candidatures existent. */
    private const SOUS_REQUETE_DERNIERE_CANDIDATURE =
        "(SELECT c2.id FROM candidature c2 WHERE c2.stagiaire_id = s.id AND c2.statut = 'acceptee'
          ORDER BY c2.date_candidature DESC, c2.id DESC LIMIT 1)";

    public function findAvecDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.poste, c.date_candidature,
                    u.nom AS compte_nom, u.prenom AS compte_prenom, u.email AS compte_email
             FROM stagiaire s
             LEFT JOIN candidature c ON c.id = " . self::SOUS_REQUETE_DERNIERE_CANDIDATURE . "
             LEFT JOIN utilisateur u ON u.id = s.utilisateur_id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Fiche stagiaire liée au compte utilisateur actuellement connecté (auto-service stagiaire). */
    public function findByUtilisateurId(int $utilisateurId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, c.poste, c.date_candidature FROM stagiaire s
             LEFT JOIN candidature c ON c.id = " . self::SOUS_REQUETE_DERNIERE_CANDIDATURE . "
             WHERE s.utilisateur_id = ?"
        );
        $stmt->execute([$utilisateurId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Règle 2 : une même personne (identifiée par son email, unique en base) ne doit
     * avoir qu'une seule fiche stagiaire, réutilisée d'une candidature à l'autre.
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stagiaire WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /** Lie une fiche stagiaire à un compte utilisateur (rôle stagiaire) pour l'auto-service. */
    public function lierCompte(int $id, int $utilisateurId): bool
    {
        return $this->update($id, ['utilisateur_id' => $utilisateurId]);
    }

    public function delierCompte(int $id): bool
    {
        return $this->update($id, ['utilisateur_id' => null]);
    }

    /** Règle 8 : suppression interdite si historique (convention/livrable/évaluation) -> archivage. */
    public function possedeHistorique(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM convention WHERE stagiaire_id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetch()['c'] > 0;
    }

    /**
     * Suppression réelle d'une fiche sans historique. Les candidatures qui ont donné
     * naissance à la fiche sont conservées (dossier de recrutement) : seul leur lien
     * vers la fiche est retiré, sans quoi la clé étrangère bloquerait la suppression.
     */
    public function supprimerSansHistorique(int $id): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE candidature SET stagiaire_id = NULL WHERE stagiaire_id = ?")->execute([$id]);
            $this->delete($id);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function archiver(int $id): bool
    {
        return $this->update($id, ['statut' => 'archive']);
    }

    public function marquerDesiste(int $id): bool
    {
        return $this->update($id, ['statut' => 'desiste']);
    }

    public function paginer(int $page, int $perPage, string $recherche = '', string $statut = '', string $etablissement = ''): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(s.nom LIKE :q1 OR s.prenom LIKE :q2 OR s.email LIKE :q3)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
            $params['q3'] = "%{$recherche}%";
        }
        if ($statut !== '') {
            $wheres[] = "s.statut = :statut";
            $params['statut'] = $statut;
        }
        if ($etablissement !== '') {
            $wheres[] = "s.etablissement = :etab";
            $params['etab'] = $etablissement;
        }
        return $this->paginateRaw(
            "SELECT s.*, (SELECT st.statut FROM stage st JOIN convention cv ON cv.id = st.convention_id
                          WHERE cv.stagiaire_id = s.id ORDER BY st.id DESC LIMIT 1) AS stage_statut",
            "FROM stagiaire s",
            $wheres, $params, $page, $perPage, "s.nom ASC"
        );
    }

    /**
     * Statistiques par établissement : nombre de stagiaires, candidatures en attente
     * et répartition des stages par statut (a_venir, en_cours, termine, interrompu).
     */
    public function statsParEtablissement(): array
    {
        $lignes = [];
        $stmt = $this->db->query(
            "SELECT s.etablissement,
                    COUNT(DISTINCT s.id) AS stagiaires,
                    SUM(CASE WHEN st.statut = 'a_venir'    THEN 1 ELSE 0 END) AS a_venir,
                    SUM(CASE WHEN st.statut = 'en_cours'   THEN 1 ELSE 0 END) AS en_cours,
                    SUM(CASE WHEN st.statut = 'termine'    THEN 1 ELSE 0 END) AS termine,
                    SUM(CASE WHEN st.statut = 'interrompu' THEN 1 ELSE 0 END) AS interrompu
             FROM stagiaire s
             LEFT JOIN convention c ON c.stagiaire_id = s.id
             LEFT JOIN stage st ON st.convention_id = c.id
             GROUP BY s.etablissement"
        );
        foreach ($stmt->fetchAll() as $r) {
            $lignes[$r['etablissement']] = self::ligneStats($r);
        }

        $stmt = $this->db->query(
            "SELECT etablissement, COUNT(*) AS n FROM candidature
             WHERE statut = 'en_attente' GROUP BY etablissement"
        );
        foreach ($stmt->fetchAll() as $r) {
            $lignes[$r['etablissement']] ??= self::ligneStats([]);
            $lignes[$r['etablissement']]['candidatures_en_attente'] = (int)$r['n'];
        }

        uksort($lignes, fn($a, $b) =>
            [$lignes[$b]['stagiaires'], $lignes[$b]['candidatures_en_attente']] <=> [$lignes[$a]['stagiaires'], $lignes[$a]['candidatures_en_attente']]
            ?: strcasecmp($a, $b));
        return $lignes;
    }

    /**
     * Statistiques par genre (sexe enregistré M/F) : candidatures, stagiaires et
     * stages par statut. Toujours deux lignes, même à zéro.
     */
    public function statsParGenre(): array
    {
        $lignes = ['F' => self::ligneStats([]), 'M' => self::ligneStats([])];

        $stmt = $this->db->query(
            "SELECT s.sexe,
                    COUNT(DISTINCT s.id) AS stagiaires,
                    SUM(CASE WHEN st.statut = 'a_venir'    THEN 1 ELSE 0 END) AS a_venir,
                    SUM(CASE WHEN st.statut = 'en_cours'   THEN 1 ELSE 0 END) AS en_cours,
                    SUM(CASE WHEN st.statut = 'termine'    THEN 1 ELSE 0 END) AS termine,
                    SUM(CASE WHEN st.statut = 'interrompu' THEN 1 ELSE 0 END) AS interrompu
             FROM stagiaire s
             LEFT JOIN convention c ON c.stagiaire_id = s.id
             LEFT JOIN stage st ON st.convention_id = c.id
             GROUP BY s.sexe"
        );
        foreach ($stmt->fetchAll() as $r) {
            if (isset($lignes[$r['sexe']])) {
                $lignes[$r['sexe']] = self::ligneStats($r);
            }
        }

        $stmt = $this->db->query(
            "SELECT sexe, COUNT(*) AS total, SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente
             FROM candidature GROUP BY sexe"
        );
        foreach ($stmt->fetchAll() as $r) {
            if (isset($lignes[$r['sexe']])) {
                $lignes[$r['sexe']]['candidatures'] = (int)$r['total'];
                $lignes[$r['sexe']]['candidatures_en_attente'] = (int)$r['en_attente'];
            }
        }
        return $lignes;
    }

    private static function ligneStats(array $r): array
    {
        return [
            'stagiaires' => (int)($r['stagiaires'] ?? 0),
            'candidatures' => 0,
            'candidatures_en_attente' => 0,
            'a_venir' => (int)($r['a_venir'] ?? 0),
            'en_cours' => (int)($r['en_cours'] ?? 0),
            'termine' => (int)($r['termine'] ?? 0),
            'interrompu' => (int)($r['interrompu'] ?? 0),
        ];
    }

    public function etablissements(): array
    {
        return $this->db->query(
            "SELECT DISTINCT etablissement FROM (
                SELECT etablissement FROM stagiaire
                UNION
                SELECT etablissement FROM candidature WHERE statut = 'en_attente'
             ) e ORDER BY etablissement"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Recherche rapide du tableau de bord : réunit dans une seule liste les stagiaires
     * déjà acceptés (table stagiaire) et les personnes encore en cours de candidature
     * (candidature au statut 'en_attente'), avec un type distinct pour chaque ligne
     * (utilisé pour afficher le badge "Stagiaire" ou "Candidat(e)").
     */
    public function rechercheRapide(int $page, int $perPage, string $recherche = '', string $etablissement = ''): array
    {
        $sousRequete = "
            SELECT s.id AS id, CONCAT(s.nom, ' ', s.prenom) AS nom_affiche, s.email AS email,
                   s.sexe AS sexe, s.etablissement AS etablissement, s.filiere AS filiere,
                   'stagiaire' AS type,
                   (SELECT st.statut FROM stage st JOIN convention cv ON cv.id = st.convention_id
                    WHERE cv.stagiaire_id = s.id ORDER BY st.id DESC LIMIT 1) AS stage_statut
            FROM stagiaire s
            UNION ALL
            SELECT c.id AS id, c.nom_complet AS nom_affiche, c.email AS email,
                   c.sexe AS sexe, c.etablissement AS etablissement, c.filiere AS filiere,
                   'candidat' AS type,
                   NULL AS stage_statut
            FROM candidature c
            WHERE c.statut = 'en_attente'
        ";

        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(nom_affiche LIKE :q1 OR email LIKE :q2)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
        }
        if ($etablissement !== '') {
            $wheres[] = "etablissement = :etab";
            $params['etab'] = $etablissement;
        }

        return $this->paginateRaw(
            "SELECT combo.*",
            "FROM ({$sousRequete}) combo",
            $wheres, $params, $page, $perPage, "nom_affiche ASC"
        );
    }
}
