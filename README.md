# IT CORP - Plateforme de gestion des stages et des stagiaires

Application web PHP MVC (sans framework externe) + MySQL, développée à partir du
cahier des charges (GUIFO KOUAM Henri David, IT CORP). Elle couvre l'intégralité
du cycle de vie d'un stage : **Candidature → Acceptation → Convention → Stage →
Évaluation → Attestation**, avec authentification, gestion des droits par rôle,
et pagination AJAX (20 lignes maximum par page) sur toutes les listes.

## 1. Prérequis

- PHP 8.0+ avec l'extension **pdo_mysql** (inclus dans WAMP / XAMPP / LAMP / MAMP)
- **MySQL 8.0.16+ ou MariaDB 10.4+** (le schéma utilise des contraintes CHECK, des
  colonnes générées et des déclencheurs)
- Un navigateur récent (Chrome, Firefox, Edge)

Aucune dépendance Composer/npm n'est nécessaire : tout est prêt à l'exécution.

## 2. Installation (WAMP / XAMPP / LAMP)

1. Copiez le dossier `gestion-stages` dans le répertoire web de votre serveur
   (ex. `htdocs/gestion-stages` pour XAMPP/WAMP, ou `www/gestion-stages`).

2. Créez la base de données en exécutant les scripts SQL **dans cet ordre**, avec
   le client en ligne de commande (les déclencheurs utilisent la directive
   `DELIMITER`, que phpMyAdmin n'interprète pas toujours correctement) :
   ```
   mysql -u root -p < sql/01_schema.sql        # tables, contraintes, vue
   mysql -u root -p < sql/02_triggers.sql      # règles de gestion (déclencheurs)
   mysql -u root -p < sql/03_referentiel.sql   # périodes d'évaluation, critères
   mysql -u root -p < sql/04_seed_dev.sql      # DÉVELOPPEMENT UNIQUEMENT : jeu d'essai + 4 comptes
   ```
   Si votre base est **déjà installée**, exécutez seulement, une seule fois :
   ```
   mysql -u root -p < sql/06_notifications.sql   # ajoute la table des notifications
   mysql -u root -p < sql/07_archivage_livrables.sql   # ajoute l'archivage des livrables
   mysql -u root -p < sql/08_mails_candidats.sql       # suivi des mails envoyés aux candidats
   ```
   Le script `sql/05_tests_regles.sql` est facultatif : il contient 24 tests
   négatifs qui doivent tous échouer (voir son en-tête). Ne l'exécutez que sur
   une base de développement.

3. Configurez l'accès à la base dans `config/config.php` (ou via variables
   d'environnement `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). Par défaut :
   utilisateur `root`, mot de passe vide (valeurs standard WAMP/XAMPP).

4. Ouvrez votre navigateur sur :
   ```
   http://localhost/gestion-stages/public/
   ```

Si vous préférez lancer un serveur PHP intégré directement (test rapide, sans
Apache) :
```
php -S localhost:8000 -t public
```
puis ouvrez `http://localhost:8000/`.

## 3. Comptes de démonstration

Mot de passe identique pour tous : **Passer123!**

| Rôle          | Email                  |
|---------------|-------------------------|
| Administrateur / Responsable des stages | admin@itcorp.co |
| Encadrant     | encadrant@itcorp.co    |
| Stagiaire     | stagiaire@itcorp.co    |
| Direction     | direction@itcorp.co    |

Ces identifiants ne sont plus affichés sur la page de connexion (pour un rendu
professionnel) : conservez-les de votre côté pour vos tests.

## 4. Structure du projet

```
gestion-stages/
├── app/
│   ├── core/          Noyau (Router, Auth, Database PDO, Controller de base, Historique)
│   ├── controllers/   Un contrôleur par module fonctionnel
│   ├── models/        Accès aux données + règles métier + pagination
│   └── views/         Vues PHP (layout + pages) et JS de pagination AJAX
├── config/config.php  Configuration base de données / application
├── public/            Racine web (point d'entrée index.php, assets, uploads)
└── sql/               01_schema, 02_triggers, 03_referentiel, 04_seed_dev, 05_tests_regles
```

## 5. Fonctionnement général

- **Authentification** par session PHP, mots de passe hashés (bcrypt).
- **Permissions par rôle** (Règle 7 du cahier des charges) : chaque contrôleur
  vérifie `Auth::requireRole(...)` avant d'exécuter une action. Un accès non
  autorisé renvoie une page 403.
- **Pagination AJAX** : chaque liste (candidatures, stagiaires, conventions,
  stages, livrables, attestations, utilisateurs) charge ses données via
  `fetch()` sur un endpoint `*/apiListe`, avec un maximum strict de **20
  lignes par page**, plafond appliqué côté serveur quel que soit le paramètre
  envoyé par le client.
- **Protection CSRF** sur tous les formulaires POST.
- **Auto-service du compte** : tout utilisateur connecté peut modifier son mot
  de passe (ancien mot de passe, nouveau mot de passe, confirmation) depuis le
  lien dans la barre du haut.
- **Connexion persistante ("Se souvenir de moi")** : case à cocher sur la page
  de connexion, jeton persistant (sélecteur/validateur haché, rotation à
  chaque utilisation) valable 30 jours, supprimé à la déconnexion.
- **Mots de passe** : à la création d'un compte, au changement et à la
  réinitialisation, le mot de passe doit commencer par une majuscule, contenir
  au moins un chiffre et au moins un caractère spécial (6 caractères minimum).
- **Cloisonnement des encadrants** : un encadrant n'accède qu'aux stages dont il
  est l'encadrant actuel (détail du stage, livrables, évaluations). Lui seul peut
  y ajouter une échéance de livrable ou saisir une évaluation.
- **Calcul automatique de la note** : l'encadrant note chaque critère de la
  grille (tous obligatoires) ; la note globale /20 est calculée par le système
  (total des notes ÷ total des barèmes × 20, arrondi à 2 décimales) et
  affichée en direct pendant la saisie. Elle n'est plus saisissable à la main.
- **Évaluations et stagiaire** : un stagiaire ne voit ses évaluations qu'une fois
  son stage terminé.
- **Bouton « Télécharger »** : télécharge le fichier déposé pour un livrable (admin et
  direction : tous les stagiaires ; encadrant : ses stagiaires ; stagiaire : ses
  propres livrables). Il n'apparaît que lorsque le livrable a été déposé et que son fichier
  existe réellement. À côté, admin et encadrant du stage disposent du bouton « Supprimer »
  (détail du stage et liste des livrables). Un dépôt exige un fichier joint. Taille maximale : celle de PHP
  (`upload_max_filesize` et `post_max_size` dans `php.ini`, 2 Mo / 8 Mo par défaut) ; au-delà,
  l'application affiche la limite et la marche à suivre pour l'augmenter.
- **Suppression = archivage** : « Supprimer » un livrable (récent ou ancien, déposé ou non)
  l'archive : il quitte les listes mais reste en base avec son fichier. Le filtre
  « Archivés (supprimés) » de la liste des livrables permet de le retrouver, de le télécharger
  et de le restaurer. Un livrable archivé ne peut plus être déposé, validé ni rejeté.
- **Notifications** (admin et encadrant) : chaque dépôt de livrable notifie
  l'encadrant actuel du stage et tous les administrateurs (cloche dans la barre
  du haut, page « Notifications »). L'affectation d'un encadrant à un stage
  notifie cet encadrant qu'un stagiaire lui a été confié.
- **Mail au candidat** : après avoir accepté ou refusé une candidature,
  l'administrateur arrive sur un formulaire pré-rempli (objet + message selon
  la décision, à partir des informations du CV saisies dans la candidature),
  modifiable avant envoi. Le bouton enveloppe de la liste des candidatures
  permet d'envoyer un mail à tout moment (modèles : acceptation, refus, accusé
  de réception). Chaque envoi est tracé dans l'historique et la date du
  dernier mail s'affiche dans la liste. Sans configuration (mode `journal`),
  les mails sont seulement enregistrés dans `storage/mails/`. **Pour un envoi
  réel** dans la boîte du candidat : copiez `config/mail.local.exemple.php` en
  `config/mail.local.php` et renseignez le compte d'envoi (Gmail :
  `smtp.gmail.com`, port 587, `tls`, avec un « mot de passe d'application »
  Google de 16 caractères, créé sur https://myaccount.google.com/apppasswords
  après activation de la validation en deux étapes). Ce fichier contient un
  mot de passe : ne le partagez pas. L'extension PHP `openssl` doit être
  activée (c'est le cas par défaut sous WAMP).
- **Espace personnel du stagiaire** : une fois son compte lié à sa fiche par
  l'administrateur (`Stagiaires → [nom] → Compte utilisateur lié`), le
  stagiaire consulte son propre dossier et ses stages depuis son tableau de
  bord, et ne peut pas accéder aux dossiers des autres stagiaires.
- **Dépôt des livrables** : seul le stagiaire titulaire du stage peut déposer
  (téléverser) son livrable, depuis son propre compte. L'admin et l'encadrant
  ne peuvent que consulter, valider ou rejeter un livrable déposé - jamais le
  déposer à la place du stagiaire, afin de conserver la traçabilité de qui a
  réellement fourni le document.
- **Sexe du stagiaire** : renseigné à la création de la candidature (et
  modifiable ensuite depuis `Stagiaires → [nom] → Modifier`), il sert
  uniquement à accorder correctement le texte de l'attestation de fin de
  stage générée automatiquement ("étudiant"/"étudiante",
  "Monsieur"/"Madame", "il"/"elle").
- **Attestation de fin de stage (PDF)** : mise en page soignée (bandeau
  d'en-tête IT CORP, titre, texte accordé au genre du stagiaire, bloc
  signature), générée sans dépendance externe.
- **Convention de stage (PDF)** : depuis `Stagiaires → [nom] → Conventions`,
  chaque convention peut être téléchargée en PDF (lien "PDF"), quel que soit
  son statut - le document reflète toujours les informations actuelles
  (utile après une prolongation). Un scan du document signé par les deux
  parties peut aussi être téléversé (PDF/JPG/PNG, réservé à l'admin) et
  reste ensuite téléchargeable via le lien "Document signé".

## 6. Règles de gestion implémentées

1. Respect de l'ordre des étapes (candidature → convention → stage → évaluation → attestation).
2. Réutilisation automatique des informations déjà saisies (candidature → fiche stagiaire → convention).
3. Un stage ne peut être créé/démarré que si sa convention est **validée**.
4. Un stagiaire doit avoir un encadrant ; l'historique des encadrants est conservé (table `affectation`).
5. Une évaluation ne peut pas être dupliquée pour la même période (contrainte unique en base + contrôle applicatif).
6. Le désistement d'un candidat/stagiaire est tracé, jamais supprimé.
7. Les droits d'accès dépendent strictement du rôle de l'utilisateur.
8. La suppression d'un stagiaire ayant un historique est refusée : il est archivé à la place.
9. *(Règle ajoutée)* Un stagiaire ne peut pas avoir deux stages en parallèle : un nouveau stage
   ne peut être créé que si tous les stages précédents de ce stagiaire sont terminés ou
   interrompus. Le bouton de création de stage est masqué dans ce cas, et la création est
   également bloquée côté serveur.

## 7. Cas limites couverts

Désistement après acceptation · convention non signée au démarrage · changement
d'encadrant en cours de stage · évaluation déjà saisie pour la période ·
candidat refusé qui recandidate · encadrant non affecté bloquant le démarrage
du stage · stage interrompu avant son terme · convention arrivant à échéance
avant la fin du stage (avec prolongation) · livrable déposé hors délai (marqué
visuellement) · génération d'attestation bloquée si aucune évaluation n'existe
· changement d'encadrant pendant une évaluation en cours (l'évaluation reste
rattachée à son auteur) · candidature en double signalée · suppression d'un
stagiaire avec historique transformée en archivage.

## 8. Notes de sécurité pour la mise en production

- Changez immédiatement les mots de passe des comptes de démonstration.
- Configurez `config/config.php` avec des identifiants dédiés (jamais `root`).
- Servez l'application en HTTPS et désactivez `display_errors` en production
  (dans `public/index.php`).
- Le générateur de PDF interne est volontairement minimaliste (aucune
  dépendance externe) ; vous pouvez le remplacer par une bibliothèque comme
  TCPDF ou Dompdf si un rendu plus riche est nécessaire.
- Le dossier `public/uploads/` contient un `.htaccess` qui interdit l'accès direct
  aux fichiers déposés (sous Apache) ; avec un autre serveur web, reproduisez cette
  interdiction, ou placez le dossier hors de la racine publique.
- Limites connues : pas de blocage temporaire après des échecs de connexion
  répétés, pas de lien de réinitialisation du mot de passe par e-mail (la
  récupération repose sur l'e-mail et le téléphone du compte).
