<?php

namespace App\Core;

/**
 * Accord en genre des textes générés par l'application (mails, PDF, notifications,
 * historique) à partir du champ "sexe" (M/F) saisi par l'administrateur lors de
 * l'enregistrement de la candidature, puis recopié sur la fiche stagiaire.
 *
 * Usage :
 *   $g = Genre::mots($candidature['sexe']);
 *   "Bonjour {$g['civilite']} ..."            -> Monsieur / Madame
 *   Genre::accorder($sexe, 'informé', 'informée')
 */
final class Genre
{
    public static function estFemme(?string $sexe): bool
    {
        return strtoupper(trim((string)$sexe)) === 'F';
    }

    /** Renvoie la forme masculine ou féminine selon le sexe. */
    public static function accorder(?string $sexe, string $masculin, string $feminin): string
    {
        return self::estFemme($sexe) ? $feminin : $masculin;
    }

    /** Vocabulaire courant déjà accordé. */
    public static function mots(?string $sexe): array
    {
        $f = self::estFemme($sexe);
        return [
            'civilite'      => $f ? 'Madame' : 'Monsieur',
            'il'          => $f ? 'elle' : 'il',
            'Il'            => $f ? 'Elle' : 'Il',
            'e'             => $f ? 'e' : '',          // accord simple : informé{e}
            'etudiant'      => $f ? 'étudiante' : 'étudiant',
            'candidat'      => $f ? 'candidate' : 'candidat',
            'le_candidat'   => $f ? 'la candidate' : 'le candidat',
            'Le_candidat'   => $f ? 'La candidate' : 'Le candidat',
            'le_stagiaire'  => $f ? 'la stagiaire' : 'le stagiaire',
            'Le_stagiaire'  => $f ? 'La stagiaire' : 'Le stagiaire',
            'un_stagiaire'  => $f ? 'une stagiaire' : 'un stagiaire',
            'Un_stagiaire'  => $f ? 'Une stagiaire' : 'Un stagiaire',
            'retenu'        => $f ? 'retenue' : 'retenu',
            'informe'       => $f ? 'informée' : 'informé',
            'designe'       => $f ? 'désignée' : 'désigné',
            'affecte'       => $f ? 'affectée' : 'affecté',
            'desiste'       => $f ? 'désistée' : 'désisté',
        ];
    }
}
