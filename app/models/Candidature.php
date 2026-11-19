<?php

namespace App\Models;

use App\Core\Model;

class Candidature extends Model
{
    protected string $table = 'candidature';

    /**
     * Cas limite : "Candidature en double pour le même poste" -> on signale
     * le doublon (même nom/email/établissement) plutôt que de bloquer.
     */
    public function detecterDoublon(string $email, string $poste, string $etablissement, ?int $ignorerId = null): ?array
    {
        $sql = "SELECT * FROM candidature WHERE email = :email AND poste = :poste AND etablissement = :etab
                AND statut IN ('en_attente','acceptee')";
        $params = ['email' => $email, 'poste' => $poste, 'etab' => $etablissement];
        if ($ignorerId) {
            $sql .= " AND id != :id";
            $params['id'] = $ignorerId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Modèles de mail proposés à l'administrateur (texte modifiable avant envoi). */
    public const MODELES_MAIL = [
        'acceptee'   => 'Acceptation',
        'refusee'    => 'Refus',
        'en_attente' => 'Accusé de réception',
    ];

    /**
     * Sujet et corps pré-remplis à partir des informations du candidat (issues de son CV).
     * Par défaut, le modèle correspond au statut actuel de la candidature.
     */
    public function modeleMail(array $c, ?string $modele = null, string $signature = 'IT CORP'): array
    {
        $modele = ($modele !== null && isset(self::MODELES_MAIL[$modele])) ? $modele : ($c['statut'] === 'desistee' ? 'acceptee' : $c['statut']);
        // Accord en genre selon le sexe saisi par l'administrateur à l'enregistrement.
        $g = \App\Core\Genre::mots($c['sexe'] ?? 'M');
        $civilite = $g['civilite'];
        $poste = $c['poste'];
        $date = date('d/m/Y', strtotime($c['date_candidature']));

        switch ($modele) {
            case 'acceptee':
                $sujet = "Votre candidature de stage ({$poste}) a été retenue";
                $texte = "Nous avons le plaisir de vous informer que votre candidature du {$date} pour un stage au poste de « {$poste} » a été acceptée : "
                    . "vous êtes {$g['retenu']} pour rejoindre IT CORP en tant que stagiaire.\n\n"
                    . "Nous reviendrons très prochainement vers vous pour les modalités pratiques (convention de stage, date de début, documents à fournir).";
                break;
            case 'refusee':
                $sujet = "Réponse à votre candidature de stage ({$poste})";
                $texte = "Nous vous remercions de l'intérêt que vous portez à notre entreprise et du temps consacré à votre candidature du {$date} pour un stage au poste de « {$poste} ».\n\n"
                    . "Après étude attentive de votre dossier, nous sommes au regret de vous informer que nous ne pouvons pas y donner une suite favorable.\n\n"
                    . "Nous conservons vos coordonnées et vous souhaitons pleine réussite dans vos projets.";
                break;
            default:
                $sujet = "Accusé de réception de votre candidature de stage ({$poste})";
                $texte = "Nous accusons bonne réception de votre candidature du {$date} pour un stage au poste de « {$poste} ».\n\n"
                    . "Votre dossier est en cours d'étude ; nous vous tiendrons {$g['informe']} de la suite qui lui sera donnée.";
        }

        return [
            'modele' => $modele,
            'sujet' => $sujet,
            'corps' => "Bonjour {$civilite} {$c['nom_complet']},\n\n{$texte}\n\nCordialement,\n{$signature}",
        ];
    }

    public function paginer(int $page, int $perPage, string $recherche = '', string $statut = ''): array
    {
        $wheres = [];
        $params = [];
        if ($recherche !== '') {
            $wheres[] = "(nom_complet LIKE :q1 OR email LIKE :q2 OR etablissement LIKE :q3 OR poste LIKE :q4)";
            $params['q1'] = "%{$recherche}%";
            $params['q2'] = "%{$recherche}%";
            $params['q3'] = "%{$recherche}%";
            $params['q4'] = "%{$recherche}%";
        }
        if ($statut !== '') {
            $wheres[] = "statut = :statut";
            $params['statut'] = $statut;
        }
        return $this->paginateRaw(
            "SELECT *",
            "FROM candidature",
            $wheres, $params, $page, $perPage, "date_candidature DESC, id DESC"
        );
    }
}
