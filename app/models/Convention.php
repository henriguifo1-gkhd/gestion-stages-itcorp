<?php

namespace App\Models;

use App\Core\Model;

class Convention extends Model
{
    protected string $table = 'convention';

    public function genererReference(): string
    {
        $annee = date('Y');
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM convention WHERE reference LIKE ?");
        $stmt->execute(["CONV-{$annee}-%"]);
        $n = (int)$stmt->fetch()['c'] + 1;
        // Le comptage peut tomber sur une référence déjà prise (saisie manuelle, ancien format) :
        // on avance jusqu'à la première référence libre pour ne jamais proposer un doublon.
        do {
            $reference = sprintf('CONV-%s-%04d', $annee, $n++);
        } while ($this->referenceExiste($reference));
        return $reference;
    }

    public function referenceExiste(string $reference): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) c FROM convention WHERE reference = ?");
        $stmt->execute([$reference]);
        return (int)$stmt->fetch()['c'] > 0;
    }

    /** Règle 3 : une convention est "valide" si statut = validee. */
    public function estValide(array $convention): bool
    {
        return $convention['statut'] === 'validee';
    }

    public function paginer(int $page, int $perPage, string $recherche = '', string $statut = ''): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(c.reference LIKE :q1 OR c.sujet LIKE :q2 OR s.nom LIKE :q3 OR s.prenom LIKE :q4)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
            $params['q3'] = "%{$recherche}%";
            $params['q4'] = "%{$recherche}%";
        }
        if ($statut !== '') {
            $wheres[] = "c.statut = :statut";
            $params['statut'] = $statut;
        }
        $resultat = $this->paginateRaw(
            "SELECT c.*, s.nom AS stagiaire_nom, s.prenom AS stagiaire_prenom",
            "FROM convention c JOIN stagiaire s ON s.id = c.stagiaire_id",
            $wheres, $params, $page, $perPage, "c.date_debut DESC"
        );
        return self::marquerDocuments($resultat);
    }

    /**
     * Pour chaque convention : le scan signé existe-t-il vraiment sur le disque, et le navigateur
     * peut-il l'afficher ? Pilote l'affichage des boutons « Ouvrir » / « Télécharger ».
     */
    public static function marquerDocuments(array $resultat): array
    {
        $racine = __DIR__ . '/../../public/';
        foreach ($resultat['data'] as &$c) {
            $c['document_existe'] = !empty($c['document_path']) && is_file($racine . $c['document_path']);
            $c['document_ouvrable'] = $c['document_existe'] && \App\Core\Controller::estOuvrable($racine . $c['document_path']);
        }
        unset($c);
        return $resultat;
    }

    public function parStagiaire(int $stagiaireId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM convention WHERE stagiaire_id = ? ORDER BY id DESC");
        $stmt->execute([$stagiaireId]);
        return self::marquerDocuments(['data' => $stmt->fetchAll()])['data'];
    }
}
