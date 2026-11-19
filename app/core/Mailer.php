<?php

namespace App\Core;

/**
 * Envoi de mails texte (UTF-8) sans dépendance externe.
 * Deux modes (config/config.php, section "mail") :
 *  - "journal" : le mail est écrit dans storage/mails/ (fichier .eml), aucun envoi réel ;
 *  - "smtp"    : envoi réel par un client SMTP minimal (STARTTLS / SSL, AUTH LOGIN).
 */
class Mailer
{
    private array $cfg;
    private string $erreur = '';
    /** Réponse finale du serveur SMTP au dernier envoi (preuve d'acceptation, ex. "250 2.0.0 OK ... - gsmtp"). */
    private string $dernierAccuse = '';

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $this->cfg = $config['mail'];
        // Expéditeur par défaut : le compte qui s'authentifie (Gmail et Outlook n'acceptent
        // pas d'envoyer au nom d'une autre adresse non déclarée).
        if (trim((string)($this->cfg['expediteur'] ?? '')) === '') {
            $this->cfg['expediteur'] = $this->cfg['utilisateur'] !== '' ? $this->cfg['utilisateur'] : 'stages@itcorp.co';
        }
        // Les mots de passe d'application Google s'affichent par groupes de 4 séparés d'espaces.
        if (stripos((string)$this->cfg['hote'], 'gmail.com') !== false) {
            $this->cfg['mot_de_passe'] = str_replace(' ', '', (string)$this->cfg['mot_de_passe']);
        }
    }

    public function mode(): string
    {
        return $this->cfg['mode'] === 'smtp' ? 'smtp' : 'journal';
    }

    /** Dernière erreur rencontrée (message lisible par l'administrateur). */
    public function erreur(): string
    {
        return $this->erreur;
    }

    /** Accusé du serveur SMTP pour le dernier mail envoyé (vide en mode journal). */
    public function dernierAccuse(): string
    {
        return $this->dernierAccuse;
    }

    public function envoyer(string $email, string $nom, string $sujet, string $corps): bool
    {
        $this->erreur = '';
        $this->dernierAccuse = '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->erreur = "L'adresse email du candidat est invalide : {$email}";
            return false;
        }
        $message = $this->construireMessage($email, $nom, $sujet, $corps);

        try {
            if ($this->mode() === 'smtp') {
                $this->envoyerSmtp($email, $message);
            } else {
                $this->enregistrerJournal($message);
            }
            return true;
        } catch (\Throwable $e) {
            $this->erreur = $e->getMessage();
            return false;
        }
    }

    private function construireMessage(string $email, string $nom, string $sujet, string $corps): string
    {
        // Retours à la ligne interdits dans les en-têtes (injection d'en-têtes).
        $propre = fn(string $s) => trim(str_replace(["\r", "\n"], ' ', $s));
        $encoder = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';

        // Pas de Message-ID ici : en mode SMTP, le serveur d'envoi (Gmail, Outlook...) en
        // attribue un authentique. Un Message-ID inventé sur le domaine gmail.com est un
        // signal de falsification que les filtres anti-spam pénalisent.
        $entetes = [
            'Date: ' . date('r'),
            'From: ' . $encoder($propre($this->cfg['nom_expediteur'])) . ' <' . $this->cfg['expediteur'] . '>',
            'Reply-To: ' . $this->cfg['expediteur'],
            'To: ' . ($nom !== '' ? $encoder($propre($nom)) . ' ' : '') . '<' . $email . '>',
            'Subject: ' . $encoder($propre($sujet)),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8; format=flowed',
            // quoted-printable (comme les vrais clients mail) plutôt que base64,
            // jugé suspect par les filtres pour un simple texte.
            'Content-Transfer-Encoding: quoted-printable',
        ];
        if ($this->mode() !== 'smtp') {
            $domaine = substr(strrchr($this->cfg['expediteur'], '@') ?: '@localhost', 1);
            $entetes[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domaine . '>';
        }
        $corps = str_replace(["\r\n", "\r"], "\n", $corps);
        $corps = str_replace("\n", "\r\n", $corps);
        return implode("\r\n", $entetes) . "\r\n\r\n" . quoted_printable_encode($corps);
    }

    private function enregistrerJournal(string $message): void
    {
        $dossier = __DIR__ . '/../../storage/mails';
        if (!is_dir($dossier) && !mkdir($dossier, 0775, true)) {
            throw new \RuntimeException('Impossible de créer le dossier storage/mails.');
        }
        $fichier = $dossier . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        if (file_put_contents($fichier, $message) === false) {
            throw new \RuntimeException('Impossible d\'écrire le mail dans storage/mails.');
        }
    }

    private function envoyerSmtp(string $destinataire, string $message): void
    {
        $c = $this->cfg;
        if ($c['utilisateur'] === '' || $c['mot_de_passe'] === '') {
            throw new \RuntimeException('Envoi réel activé mais compte SMTP non configuré : renseignez utilisateur et mot_de_passe dans config/mail.local.php.');
        }
        $adresse =($c['chiffrement'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['hote'] . ':' . $c['port'];
        $socket = @stream_socket_client($adresse, $errno, $errstr, 15);
        if (!$socket) {
            throw new \RuntimeException("Connexion au serveur SMTP {$c['hote']}:{$c['port']} impossible ({$errstr}).");
        }
        stream_set_timeout($socket, 20);

        // Nom de machine annoncé au serveur (EHLO) : un nom réel plutôt que "localhost".
        $ehlo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)gethostname()) ?: 'gestion-stages.local';
        if (strpos($ehlo, '.') === false) {
            $ehlo .= '.local';
        }

        try {
            $this->attendre($socket, [220]);
            $this->commande($socket, 'EHLO ' . $ehlo, [250]);
            if ($c['chiffrement'] === 'tls') {
                $this->commande($socket, 'STARTTLS', [220]);
                $methode = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
                if (!@stream_socket_enable_crypto($socket, true, $methode)) {
                    throw new \RuntimeException('Échec de la négociation TLS avec le serveur SMTP (extension openssl activée ?).');
                }
                $this->commande($socket, 'EHLO ' . $ehlo, [250]);
            }
            if ($c['utilisateur'] !== '') {
                $this->commande($socket, 'AUTH LOGIN', [334]);
                $this->commande($socket, base64_encode($c['utilisateur']), [334]);
                $this->commande($socket, base64_encode($c['mot_de_passe']), [235],
                    'Authentification SMTP refusée pour ' . $c['utilisateur'] . '. Vérifiez l\'adresse et le mot de passe dans config/mail.local.php'
                    . (stripos((string)$c['hote'], 'gmail.com') !== false
                        ? ' (Gmail exige un « mot de passe d\'application » de 16 caractères, pas le mot de passe habituel du compte).' : '.'));
            }
            $this->commande($socket, 'MAIL FROM:<' . $c['expediteur'] . '>', [250]);
            $this->commande($socket, 'RCPT TO:<' . $destinataire . '>', [250, 251]);
            $this->commande($socket, 'DATA', [354]);
            // "Dot-stuffing" (RFC 5321) : une ligne commençant par "." est doublée, sinon
            // une ligne réduite à "." terminerait le message prématurément.
            $message = preg_replace('/^\./m', '..', $message);
            $this->dernierAccuse = $this->commande($socket, $message . "\r\n.", [250]);
            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function commande($socket, string $ligne, array $codesAttendus, ?string $messageErreur = null): string
    {
        fwrite($socket, $ligne . "\r\n");
        return $this->attendre($socket, $codesAttendus, $messageErreur);
    }

    /** Lit une réponse SMTP (éventuellement multi-lignes), vérifie son code et la renvoie. */
    private function attendre($socket, array $codesAttendus, ?string $messageErreur = null): string
    {
        $reponse = '';
        while (($ligne = fgets($socket, 515)) !== false) {
            $reponse .= $ligne;
            if (strlen($ligne) < 4 || $ligne[3] === ' ') {
                break;
            }
        }
        $code = (int)substr($reponse, 0, 3);
        if (!in_array($code, $codesAttendus, true)) {
            throw new \RuntimeException($messageErreur ?? ('Le serveur SMTP a refusé l\'envoi : ' . trim($reponse ?: 'aucune réponse (délai dépassé)')));
        }
        return trim($reponse);
    }
}
