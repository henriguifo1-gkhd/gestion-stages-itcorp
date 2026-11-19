<?php
/**
 * Modèle de configuration pour l'ENVOI RÉEL des mails aux candidats.
 * 1. Copiez ce fichier sous le nom  config/mail.local.php
 * 2. Renseignez le compte d'envoi ci-dessous.
 * Ne partagez jamais mail.local.php : il contient un mot de passe.
 *
 * Gmail : activez la validation en deux étapes sur le compte, puis créez un
 * « mot de passe d'application » (https://myaccount.google.com/apppasswords).
 * C'est ce code de 16 caractères qu'il faut mettre dans mot_de_passe,
 * PAS le mot de passe habituel du compte Google.
 */
return [
    'mode'           => 'smtp',
    'hote'           => 'smtp.gmail.com',   // Outlook/Hotmail : smtp-mail.outlook.com
    'port'           => 587,
    'chiffrement'    => 'tls',              // 587 = "tls" ; 465 = "ssl"
    'utilisateur'    => 'votre.adresse@gmail.com',
    'mot_de_passe'   => 'xxxx xxxx xxxx xxxx',
    'expediteur'     => '',                 // vide = même adresse que "utilisateur"
    'nom_expediteur' => 'IT CORP - Service des stages',
];
