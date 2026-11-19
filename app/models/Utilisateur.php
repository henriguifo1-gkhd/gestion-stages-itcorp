<?php

namespace App\Models;

use App\Core\Model;

class Utilisateur extends Model
{
    protected string $table = 'utilisateur';

    /** Longueur minimale d'un mot de passe. */
    public const MDP_LONGUEUR_MIN = 6;

    /** Rappel des règles, affiché sous les champs de saisie d'un mot de passe. */
    public const MDP_REGLES = 'Le mot de passe doit commencer par une lettre majuscule, contenir au moins un chiffre et au moins un caractère spécial (par exemple ! @ # $ % & * ?), avec 6 caractères minimum.';

    /**
     * Vérifie un nouveau mot de passe. Retourne un message d'erreur qui explique
     * la règle enfreinte, ou null si le mot de passe est valide.
     * Règles : première lettre en majuscule, au moins un chiffre, au moins un
     * caractère spécial (ni lettre, ni chiffre, ni espace).
     */
    public static function erreurMotDePasse(string $mdp): ?string
    {
        if (mb_strlen($mdp, 'UTF-8') < self::MDP_LONGUEUR_MIN) {
            return 'Le mot de passe doit contenir au moins ' . self::MDP_LONGUEUR_MIN . ' caractères.';
        }
        if (!preg_match('/^\p{Lu}/u', $mdp)) {
            return 'Le mot de passe doit commencer par une lettre majuscule.';
        }
        if (!preg_match('/\d/', $mdp)) {
            return 'Le mot de passe doit contenir au moins un chiffre.';
        }
        if (!preg_match('/[^\p{L}\p{N}\s]/u', $mdp)) {
            return 'Le mot de passe doit contenir au moins un caractère spécial (par exemple ! @ # $ % & * ?).';
        }
        return null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM utilisateur WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Mot de passe oublié : aucun serveur d'email/SMS n'étant disponible en local,
     * l'identité est vérifiée par correspondance email + téléphone (compte actif).
     */
    public function findByEmailEtTelephone(string $email, string $telephone): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM utilisateur WHERE email = ? AND telephone = ? AND actif = 1"
        );
        $stmt->execute([$email, $telephone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Utilisateurs ayant le rôle encadrant (seuls autorisés à être affectés - Fonctionnalité transverse). */
    public function encadrants(): array
    {
        $stmt = $this->db->query("SELECT id, nom, prenom, email FROM utilisateur WHERE role = 'encadrant' AND actif = 1 ORDER BY nom");
        return $stmt->fetchAll();
    }

    /** Comptes de rôle "stagiaire" pas encore liés à une fiche stagiaire (pour le formulaire de liaison). */
    public function stagiairesNonLies(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id, u.nom, u.prenom, u.email FROM utilisateur u
             WHERE u.role = 'stagiaire' AND u.actif = 1
               AND NOT EXISTS (SELECT 1 FROM stagiaire s WHERE s.utilisateur_id = u.id)
             ORDER BY u.nom"
        );
        return $stmt->fetchAll();
    }

    public function creer(array $d): int
    {
        return $this->insert([
            'nom' => $d['nom'],
            'prenom' => $d['prenom'],
            'email' => $d['email'],
            'mot_de_passe' => password_hash($d['mot_de_passe'], PASSWORD_BCRYPT),
            'role' => $d['role'],
            'telephone' => $d['telephone'] ?? null,
            'actif' => $d['actif'] ?? 1,
        ]);
    }

    public function paginer(int $page, int $perPage, string $recherche = '', string $role = ''): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(nom LIKE :q1 OR prenom LIKE :q2 OR email LIKE :q3)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
            $params['q3'] = "%{$recherche}%";
        }
        if ($role !== '') {
            $wheres[] = "role = :role";
            $params['role'] = $role;
        }
        return $this->paginateRaw(
            "SELECT id, nom, prenom, email, role, actif, created_at",
            "FROM utilisateur",
            $wheres, $params, $page, $perPage, "nom ASC"
        );
    }
}
