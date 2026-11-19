<?php

namespace App\Core;

/**
 * Journal d'audit simple : trace tout évènement métier important
 * (désistement, changement d'encadrant, hors délai, etc. - Règle 6 & cas limites).
 */
class Historique
{
    public static function log(string $entite, int $entiteId, string $evenement, string $description = ''): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO historique (entite, entite_id, evenement, description, utilisateur_id) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$entite, $entiteId, $evenement, $description, Auth::id()]);
    }

    public static function pour(string $entite, int $entiteId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT h.*, u.nom, u.prenom FROM historique h LEFT JOIN utilisateur u ON u.id = h.utilisateur_id WHERE entite = ? AND entite_id = ? ORDER BY h.created_at DESC");
        $stmt->execute([$entite, $entiteId]);
        return $stmt->fetchAll();
    }
}
