<?php
/**
 * Configuration générale de l'application.
 * Modifiez ces valeurs selon votre environnement (WAMP/XAMPP/LAMP).
 */
// Identifiants d'envoi réel des mails : placés dans config/mail.local.php (fichier
// propre à chaque installation, à ne jamais partager), qui surcharge la section "mail".
$mailLocal = is_file(__DIR__ . '/mail.local.php') ? (require __DIR__ . '/mail.local.php') : [];
if (!is_array($mailLocal)) {
    $mailLocal = []; // fichier vide ou mal rempli : on reste en mode journal plutôt que de planter
}

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'gestion_stages',
        'user'     => getenv('DB_USER') ?: 'root',
        'pass'     => getenv('DB_PASS') ?: '',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'      => 'IT CORP - Gestion des stages',
        // base_url : chemin depuis la racine du domaine vers /public
        // Exemple si le projet est dans http://localhost/gestion-stages/public
        'base_url'  => '',
        'pagination_par_page' => 20, // maximum 20 lignes par page (pagination AJAX)
        'timezone'  => 'Africa/Libreville',
    ],
    // Envoi des mails aux candidats (décision sur la candidature).
    // mode "journal" : aucun envoi réel, chaque mail est enregistré dans storage/mails/
    //                  (fichier .eml lisible avec Outlook/Thunderbird) - utile en local.
    // mode "smtp"    : envoi réel via un serveur SMTP (Gmail, Outlook, hébergeur...).
    //                  Gmail : port 587 + chiffrement "tls" + un "mot de passe d'application".
    // Pour activer l'envoi réel : copier config/mail.local.exemple.php en config/mail.local.php
    // et y renseigner le compte d'envoi.
    'mail' => array_merge([
        'mode'            => getenv('MAIL_MODE') ?: 'journal',
        'hote'            => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'port'            => (int)(getenv('MAIL_PORT') ?: 587),
        'chiffrement'     => getenv('MAIL_ENCRYPTION') ?: 'tls', // "tls" (STARTTLS), "ssl" ou "" (aucun)
        'utilisateur'     => getenv('MAIL_USER') ?: '',
        'mot_de_passe'    => getenv('MAIL_PASS') ?: '',
        // Vide = l'adresse du compte d'envoi (Gmail/Outlook refusent ou réécrivent une autre adresse).
        'expediteur'      => getenv('MAIL_FROM') ?: '',
        'nom_expediteur'  => getenv('MAIL_FROM_NAME') ?: 'IT CORP - Service des stages',
    ], $mailLocal),
];
