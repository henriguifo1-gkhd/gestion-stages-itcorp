<?php

namespace App\Core;

/**
 * Contrôleur de base : rendu de vues avec layout + helpers CSRF/flash.
 */
abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $data['user'] = $data['user'] ?? Auth::user();
        if (!array_key_exists('old', $data)) {
            Auth::start();
            $data['old'] = $_SESSION['old'] ?? [];
            unset($_SESSION['old']);
        }
        extract($data);
        $content = function () use ($view, $data) {
            extract($data);
            require __DIR__ . "/../views/{$view}.php";
        };
        require __DIR__ . "/../views/{$layout}.php";
    }

    protected function viewPlain(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . "/../views/{$view}.php";
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Types que le navigateur sait afficher lui-même (bouton « Ouvrir »). */
    protected const TYPES_OUVRABLES = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'text/plain'];

    /** Type réel d'un fichier, déterminé par son contenu (et non par son extension). */
    public static function mimeFichier(string $cheminAbsolu): string
    {
        // Objet finfo (libéré automatiquement) : finfo_close() est obsolète depuis PHP 8.5 et son
        // avertissement « Deprecated » corromprait les téléchargements et les réponses JSON.
        return (new \finfo(FILEINFO_MIME_TYPE))->file($cheminAbsolu) ?: 'application/octet-stream';
    }

    /**
     * Vrai si le navigateur affichera ce fichier au lieu de le télécharger. C'est la MÊME règle
     * que celle d'envoyerFichier() : un bouton « Ouvrir » n'est affiché que s'il ouvre vraiment ;
     * pour les autres fichiers (Word, Excel, ZIP...), seul « Télécharger » est proposé.
     */
    public static function estOuvrable(?string $cheminAbsolu): bool
    {
        return $cheminAbsolu !== null && $cheminAbsolu !== '' && is_file($cheminAbsolu)
            && in_array(self::mimeFichier($cheminAbsolu), self::TYPES_OUVRABLES, true);
    }

    /**
     * Envoie un fichier au navigateur : affiché dans un nouvel onglet si ?mode=ouvrir et que le
     * type s'y prête (PDF, image, texte), téléchargé sinon. Les droits sont vérifiés par l'appelant.
     */
    protected function envoyerFichier(string $chemin, string $nomFichier, ?string $contenu = null): void
    {
        $mime = $contenu === null ? self::mimeFichier($chemin) : 'application/pdf';
        $ouvrir = $this->input('mode') === 'ouvrir' && in_array($mime, self::TYPES_OUVRABLES, true);
        $nomFichier = str_replace(['"', "\r", "\n", '/', '\\'], '_', $nomFichier);

        while (ob_get_level() > 0) {
            ob_end_clean(); // aucun octet parasite ne doit précéder le fichier
        }
        header('Content-Type: ' . $mime . ($mime === 'text/plain' ? '; charset=utf-8' : ''));
        header('Content-Disposition: ' . ($ouvrir ? 'inline' : 'attachment') . '; filename="' . $nomFichier . '"');
        header('Content-Length: ' . ($contenu === null ? filesize($chemin) : strlen($contenu)));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        if ($contenu === null) {
            readfile($chemin);
        } else {
            echo $contenu;
        }
        exit;
    }

    protected function csrfField(): string
    {
        Auth::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
    }

    protected function checkCsrf(): void
    {
        Auth::start();
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die('Session expirée ou jeton CSRF invalide. Merci de recharger le formulaire.');
        }
    }

    protected function flash(string $type, string $message): void
    {
        Auth::start();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /** Conserve les champs saisis pour les réafficher dans le formulaire après une erreur de validation. */
    protected function rememberOldInput(array $data): void
    {
        Auth::start();
        $_SESSION['old'] = $data;
    }

    // =====================================================================
    // Génération de PDF "maison" (sans dépendance externe), partagée par tous
    // les documents générés par l'application (attestations, conventions...).
    // =====================================================================

    /** Date au format long français ("24 septembre 2026"). */
    protected function dateFr(string $ymd): string
    {
        $mois = [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
            7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'];
        $ts = strtotime($ymd);
        if (!$ts) {
            return $ymd;
        }
        return (int)date('j', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /**
     * Largeurs (1/1000 em) des caractères Times-Roman (métriques AFM standard), utilisées
     * uniquement pour centrer les titres et découper le texte aux marges.
     */
    protected const LARGEURS_TIMES = [
        ' ' => 250, '!' => 333, '"' => 408, '#' => 500, '$' => 500, '%' => 833, '&' => 778, "'" => 180,
        '(' => 333, ')' => 333, '*' => 500, '+' => 564, ',' => 250, '-' => 333, '.' => 250, '/' => 278,
        '0' => 500, '1' => 500, '2' => 500, '3' => 500, '4' => 500, '5' => 500, '6' => 500, '7' => 500, '8' => 500, '9' => 500,
        ':' => 278, ';' => 278, '<' => 564, '=' => 564, '>' => 564, '?' => 444, '@' => 921,
        'A' => 722, 'B' => 667, 'C' => 667, 'D' => 722, 'E' => 611, 'F' => 556, 'G' => 722, 'H' => 722, 'I' => 333, 'J' => 389,
        'K' => 722, 'L' => 611, 'M' => 889, 'N' => 722, 'O' => 722, 'P' => 556, 'Q' => 722, 'R' => 667, 'S' => 556, 'T' => 611,
        'U' => 722, 'V' => 722, 'W' => 944, 'X' => 722, 'Y' => 722, 'Z' => 611,
        '[' => 333, '\\' => 278, ']' => 333, '^' => 469, '_' => 500, '`' => 333,
        'a' => 444, 'b' => 500, 'c' => 444, 'd' => 500, 'e' => 444, 'f' => 333, 'g' => 500, 'h' => 500, 'i' => 278, 'j' => 278,
        'k' => 500, 'l' => 278, 'm' => 778, 'n' => 500, 'o' => 500, 'p' => 500, 'q' => 500, 'r' => 333, 's' => 389, 't' => 278,
        'u' => 500, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 444,
        '{' => 480, '|' => 200, '}' => 480, '~' => 541,
        'À' => 722, 'Â' => 722, 'Ç' => 667, 'É' => 611, 'È' => 611, 'Ê' => 611, 'Ë' => 611,
        'Î' => 333, 'Ï' => 333, 'Ô' => 722, 'Ù' => 722, 'Û' => 722, 'Ü' => 722,
        'à' => 444, 'â' => 444, 'ç' => 444, 'é' => 444, 'è' => 444, 'ê' => 444, 'ë' => 444,
        'î' => 278, 'ï' => 278, 'ô' => 500, 'ù' => 500, 'û' => 500, 'ü' => 500, '°' => 400, '·' => 250,
    ];

    /** Largeur approximative (en points) d'un texte à une taille de police donnée. */
    protected function largeurTexte(string $texte, float $taille, bool $gras = false): float
    {
        $largeur = 0;
        foreach (mb_str_split($texte, 1, 'UTF-8') as $car) {
            $largeur += self::LARGEURS_TIMES[$car] ?? 500;
        }
        $l = $largeur / 1000 * $taille;
        return $gras ? $l * 1.08 : $l;   // Times-Bold est environ 8 % plus large
    }

    /** Découpe un texte en lignes ne dépassant pas la largeur maximale donnée. */
    protected function decouperTexte(string $texte, float $largeurMax, float $taille): array
    {
        $mots = preg_split('/\s+/u', trim($texte));
        $lignes = [];
        $ligne = '';
        foreach ($mots as $mot) {
            $essai = $ligne === '' ? $mot : $ligne . ' ' . $mot;
            if ($ligne === '' || $this->largeurTexte($essai, $taille) <= $largeurMax) {
                $ligne = $essai;
            } else {
                $lignes[] = $ligne;
                $ligne = $mot;
            }
        }
        if ($ligne !== '') {
            $lignes[] = $ligne;
        }
        return $lignes;
    }

    /** Échappe et convertit un texte UTF-8 en Windows-1252 pour une chaîne littérale PDF. */
    protected function pdfEchapper(string $texteUtf8): string
    {
        $cp1252 = @mb_convert_encoding($texteUtf8, 'Windows-1252', 'UTF-8');
        if ($cp1252 === false) {
            $cp1252 = $texteUtf8;
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $cp1252);
    }

    protected function pdfTexte(string $texte, float $x, float $y, float $taille, string $police = 'F1'): string
    {
        return "BT /{$police} {$taille} Tf 1 0 0 1 " . round($x, 2) . " " . round($y, 2)
            . " Tm (" . $this->pdfEchapper($texte) . ") Tj ET\n";
    }

    protected function pdfTexteCouleur(string $texte, float $x, float $y, float $taille, array $rgb, string $police = 'F1'): string
    {
        return sprintf("%.3f %.3f %.3f rg\n", $rgb[0], $rgb[1], $rgb[2])
            . $this->pdfTexte($texte, $x, $y, $taille, $police)
            . "0 0 0 rg\n";
    }

    protected function pdfRect(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n0 0 0 rg\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $w, $h);
    }

    protected function pdfRectContour(float $x, float $y, float $w, float $h, array $rgb, float $epaisseur = 1.0): string
    {
        return sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f %.2f %.2f re S\n0 0 0 RG\n", $rgb[0], $rgb[1], $rgb[2], $epaisseur, $x, $y, $w, $h);
    }

    protected function pdfLigne(float $x1, float $y1, float $x2, float $y2, array $rgb = [0.6, 0.6, 0.6], float $epaisseur = 0.8): string
    {
        return sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f m %.2f %.2f l S\n0 0 0 RG\n", $rgb[0], $rgb[1], $rgb[2], $epaisseur, $x1, $y1, $x2, $y2);
    }

    /** Assemble les objets PDF fournis en un document complet, avec table xref valide. */
    protected function assemblerPdf(array $objets): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objets as $num => $corps) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$corps}\nendobj\n";
        }
        $xrefStart = strlen($pdf);
        $count = count($objets) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<</Size {$count}/Root 1 0 R>>\nstartxref\n{$xrefStart}\n%%EOF";
        return $pdf;
    }

    /** Objets PDF communs (catalogue/pages/page/polices) pour un document A4 une page, texte seul. */
    protected function pdfObjetsPageSimple(string $contenu): array
    {
        return [
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Resources<</Font<</F1 4 0 R/F2 5 0 R>>>>/Contents 6 0 R>>',
            4 => '<</Type/Font/Subtype/Type1/BaseFont/Times-Roman/Encoding/WinAnsiEncoding>>',
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Times-Bold/Encoding/WinAnsiEncoding>>',
            6 => "<</Length " . strlen($contenu) . ">>\nstream\n{$contenu}\nendstream",
        ];
    }
}
