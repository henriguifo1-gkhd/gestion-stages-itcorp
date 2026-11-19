-- =====================================================================
-- IT CORP - Plateforme de gestion de stages et de stagiaires
-- Schéma v2 (révisé) - structure uniquement
--   01_schema.sql   : tables, contraintes, vues      (tous environnements)
--   02_triggers.sql : règles de gestion appliquées en base
--   03_seed_dev.sql : données de démonstration        (DÉVELOPPEMENT UNIQUEMENT)
-- Cible : MySQL >= 8.0.16 (CHECK appliqués) ou MariaDB >= 10.4
-- Ce script ne contient AUCUN DROP : il échoue si les tables existent
-- déjà, au lieu d'effacer des données. Les évolutions passent par des
-- scripts de migration numérotés.
-- =====================================================================
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS gestion_stages
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_stages;

-- ---------------------------------------------------------------------
-- Utilisateur : porte les 4 rôles (Règle 7 : droits d'accès par rôle).
-- Choix assumé : une seule table + ENUM, les rôles n'ayant pas
-- d'attributs propres. La cohérence rôle/usage est contrôlée par
-- triggers (02_triggers.sql).
-- ---------------------------------------------------------------------
CREATE TABLE utilisateur (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  nom                  VARCHAR(100) NOT NULL,
  prenom               VARCHAR(100) NOT NULL,
  email                VARCHAR(150) NOT NULL,
  mot_de_passe         VARCHAR(255) NOT NULL,              -- hash bcrypt/argon2 uniquement
  role                 ENUM('admin','encadrant','stagiaire','direction') NOT NULL,
  telephone            VARCHAR(30)  NULL,
  actif                TINYINT(1)   NOT NULL DEFAULT 1,
  doit_changer_mdp     TINYINT(1)   NOT NULL DEFAULT 1,     -- forcé à la 1re connexion
  tentatives_echouees  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  verrouille_jusqu_a   DATETIME     NULL,                   -- verrouillage anti-bruteforce
  derniere_connexion   DATETIME     NULL,
  mdp_modifie_le       DATETIME     NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_utilisateur_email UNIQUE (email),
  INDEX idx_utilisateur_role (role, actif)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Jetons "Se souvenir de moi" (sélecteur + validateur haché SHA-256).
-- Rotation à chaque utilisation et purge des jetons expirés côté appli ;
-- tous les jetons d'un utilisateur sont supprimés au changement de mdp.
-- ---------------------------------------------------------------------
CREATE TABLE remember_token (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT NOT NULL,
  selector        CHAR(24) NOT NULL,
  validator_hash  CHAR(64) NOT NULL,
  expires_at      DATETIME NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_remember_selector UNIQUE (selector),
  CONSTRAINT fk_remember_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE CASCADE,
  INDEX idx_remember_utilisateur (utilisateur_id),
  INDEX idx_remember_expires (expires_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Réinitialisation de mot de passe (jeton à usage unique, haché, court)
-- ---------------------------------------------------------------------
CREATE TABLE password_reset (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT NOT NULL,
  selector        CHAR(24) NOT NULL,
  validator_hash  CHAR(64) NOT NULL,
  expires_at      DATETIME NOT NULL,                 -- 30 min recommandé
  utilise_le      DATETIME NULL,
  ip_demande      VARCHAR(45) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_reset_selector UNIQUE (selector),
  CONSTRAINT fk_reset_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE CASCADE,
  INDEX idx_reset_expires (expires_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Stagiaire : fiche d'identité de référence, créée à la 1re candidature
-- acceptée puis RÉUTILISÉE pour les candidatures suivantes (Règle 2).
-- Jamais supprimé s'il a un historique -> statut 'archive'.
-- ---------------------------------------------------------------------
CREATE TABLE stagiaire (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT NULL,
  nom             VARCHAR(100) NOT NULL,
  prenom          VARCHAR(100) NOT NULL,
  sexe            CHAR(1) NOT NULL,                     -- pas de défaut (un ENUM NOT NULL prendrait 'M' implicitement)
  email           VARCHAR(150) NOT NULL,
  telephone       VARCHAR(30)  NULL,
  etablissement   VARCHAR(150) NOT NULL,
  filiere         VARCHAR(150) NULL,
  niveau_etude    VARCHAR(50)  NULL,
  statut          ENUM('actif','desiste','archive') NOT NULL DEFAULT 'actif',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_stagiaire_sexe CHECK (sexe IN ('M','F')),
  CONSTRAINT uq_stagiaire_email       UNIQUE (email),          -- une seule fiche par personne
  CONSTRAINT uq_stagiaire_utilisateur UNIQUE (utilisateur_id),
  CONSTRAINT fk_stagiaire_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE SET NULL,
  INDEX idx_stagiaire_statut (statut),
  INDEX idx_stagiaire_etablissement (etablissement)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Candidature : point d'entrée du cycle (Règle 1).
-- Le lien est inversé par rapport à la v1 : c'est la candidature qui
-- pointe vers le stagiaire (0..1), un stagiaire pouvant avoir plusieurs
-- candidatures (Règle 2). Les données saisies sont conservées telles
-- quelles (valeur de preuve) ; la fiche stagiaire fait référence ensuite.
-- ---------------------------------------------------------------------
CREATE TABLE candidature (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  stagiaire_id      INT NULL,                          -- renseigné à l'acceptation
  nom_complet       VARCHAR(150) NOT NULL,
  sexe              CHAR(1) NOT NULL,                   -- saisie obligatoire, pas de défaut implicite
  email             VARCHAR(150) NOT NULL,
  telephone         VARCHAR(30)  NULL,
  etablissement     VARCHAR(150) NOT NULL,
  filiere           VARCHAR(150) NULL,
  niveau_etude      VARCHAR(50)  NULL,
  poste             VARCHAR(150) NOT NULL,
  statut            ENUM('en_attente','acceptee','refusee','desistee') NOT NULL DEFAULT 'en_attente',
  date_candidature  DATE NOT NULL,
  dernier_mail_le   DATETIME NULL,                      -- dernier mail envoyé au candidat
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_candidature_sexe CHECK (sexe IN ('M','F')),
  CONSTRAINT fk_candidature_stagiaire FOREIGN KEY (stagiaire_id) REFERENCES stagiaire(id),
  -- un lien vers un stagiaire n'existe que si la candidature a été acceptée
  -- (ou acceptée puis désistée, le désistement étant tracé dans historique)
  CONSTRAINT chk_candidature_lien CHECK (
    stagiaire_id IS NULL OR statut IN ('acceptee','desistee')),
  INDEX idx_cand_doublon (email, poste, etablissement),   -- détection de doublons (appli)
  INDEX idx_cand_statut (statut)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Convention (Règle 3 : un stage doit reposer sur une convention validée)
-- ---------------------------------------------------------------------
CREATE TABLE convention (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  stagiaire_id   INT NOT NULL,
  reference      VARCHAR(50)  NOT NULL,
  type           VARCHAR(100) NOT NULL,                 -- à passer en ENUM/référentiel une fois la liste arrêtée
  sujet          VARCHAR(255) NOT NULL,
  date_debut     DATE NOT NULL,
  date_fin       DATE NOT NULL,
  statut         ENUM('en_attente','validee','rejetee','terminee') NOT NULL DEFAULT 'en_attente',
  document_path  VARCHAR(255) NULL,                     -- hors racine web, nom aléatoire
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_convention_reference UNIQUE (reference),
  CONSTRAINT fk_convention_stagiaire FOREIGN KEY (stagiaire_id) REFERENCES stagiaire(id),
  CONSTRAINT chk_convention_dates CHECK (date_fin >= date_debut),
  INDEX idx_convention_statut (statut)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Stage : exactement une convention, au plus un stage par convention.
-- Plus de stagiaire_id (déduit de la convention) ni d'encadrant_actuel_id
-- (déduit de l'affectation ouverte, cf. vue v_stage_detail).
-- ---------------------------------------------------------------------
CREATE TABLE stage (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  convention_id  INT NOT NULL,
  date_debut     DATE NOT NULL,                         -- dates effectives, dans la convention
  date_fin       DATE NOT NULL,
  statut         ENUM('a_venir','en_cours','termine','interrompu') NOT NULL DEFAULT 'a_venir',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_stage_convention UNIQUE (convention_id),
  CONSTRAINT fk_stage_convention FOREIGN KEY (convention_id) REFERENCES convention(id),
  CONSTRAINT chk_stage_dates CHECK (date_fin >= date_debut),
  INDEX idx_stage_statut (statut)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Affectation : historique des encadrants (Règle 4).
-- Au plus UNE affectation ouverte (date_fin NULL) par stage : la colonne
-- générée vaut stage_id si ouverte, NULL sinon, et porte un UNIQUE
-- (les NULL multiples sont autorisés) -> index unique partiel portable.
-- ---------------------------------------------------------------------
CREATE TABLE affectation (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  stage_id         INT NOT NULL,
  encadrant_id     INT NOT NULL,
  date_debut       DATE NOT NULL,
  date_fin         DATE NULL,
  motif_fin        VARCHAR(255) NULL,
  stage_si_ouverte INT AS (IF(date_fin IS NULL, stage_id, NULL)) STORED,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_affectation_stage FOREIGN KEY (stage_id) REFERENCES stage(id),
  CONSTRAINT fk_affectation_encadrant FOREIGN KEY (encadrant_id) REFERENCES utilisateur(id),
  CONSTRAINT chk_affectation_dates CHECK (date_fin IS NULL OR date_fin >= date_debut),
  CONSTRAINT uq_affectation_une_ouverte UNIQUE (stage_si_ouverte),
  INDEX idx_affectation_stage (stage_id),
  INDEX idx_affectation_encadrant (encadrant_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Livrable : hors_delai est CALCULÉ (plus de saisie manuelle possible)
-- ---------------------------------------------------------------------
CREATE TABLE livrable (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  stage_id           INT NOT NULL,
  titre              VARCHAR(150) NOT NULL,
  date_echeance      DATE NOT NULL,
  date_depot         DATETIME NULL,
  hors_delai         TINYINT(1) AS (
                       IF(date_depot IS NOT NULL AND DATE(date_depot) > date_echeance, 1, 0)
                     ) STORED,
  statut_validation  ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente',
  valide_par         INT NULL,                           -- encadrant ayant statué
  date_validation    DATETIME NULL,
  commentaire        TEXT NULL,
  fichier_path       VARCHAR(255) NULL,
  archive            TINYINT(1) NOT NULL DEFAULT 0,      -- 1 = « supprimé » (archivé, jamais effacé)
  date_archivage     DATETIME NULL,
  archive_par        INT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_livrable_archiveur FOREIGN KEY (archive_par) REFERENCES utilisateur(id),
  CONSTRAINT chk_livrable_archive CHECK (archive IN (0, 1)),
  CONSTRAINT fk_livrable_stage FOREIGN KEY (stage_id) REFERENCES stage(id),
  CONSTRAINT fk_livrable_valideur FOREIGN KEY (valide_par) REFERENCES utilisateur(id),
  -- statuer suppose un dépôt, un valideur et une date ; en attente = ni l'un ni l'autre
  CONSTRAINT chk_livrable_validation CHECK (
       (statut_validation = 'en_attente' AND valide_par IS NULL AND date_validation IS NULL)
    OR (statut_validation IN ('valide','rejete') AND date_depot IS NOT NULL
        AND valide_par IS NOT NULL AND date_validation IS NOT NULL)),
  INDEX idx_livrable_stage (stage_id),
  INDEX idx_livrable_statut (statut_validation),
  INDEX idx_livrable_archive (archive)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Évaluation (Règle 5 : une seule évaluation par stage et par période).
-- Période = table de référence (fin des saisies libres "S1"/"s1").
-- Grille = tables critere / evaluation_critere (au lieu d'un TEXT libre).
-- ---------------------------------------------------------------------
CREATE TABLE periode_evaluation (
  code     VARCHAR(20)  PRIMARY KEY,
  libelle  VARCHAR(100) NOT NULL,
  ordre    TINYINT UNSIGNED NOT NULL,
  CONSTRAINT uq_periode_ordre UNIQUE (ordre)
) ENGINE=InnoDB;

CREATE TABLE critere (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  libelle  VARCHAR(150) NOT NULL,
  bareme   DECIMAL(4,2) NOT NULL DEFAULT 20.00,
  actif    TINYINT(1)   NOT NULL DEFAULT 1,
  CONSTRAINT uq_critere_libelle UNIQUE (libelle),
  CONSTRAINT chk_critere_bareme CHECK (bareme > 0)
) ENGINE=InnoDB;

CREATE TABLE evaluation (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  stage_id               INT NOT NULL,
  encadrant_id           INT NOT NULL,
  periode_code           VARCHAR(20) NOT NULL,
  note_globale           DECIMAL(4,2) NOT NULL,
  appreciation_generale  TEXT NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_evaluation_stage FOREIGN KEY (stage_id) REFERENCES stage(id),
  CONSTRAINT fk_evaluation_encadrant FOREIGN KEY (encadrant_id) REFERENCES utilisateur(id),
  CONSTRAINT fk_evaluation_periode FOREIGN KEY (periode_code) REFERENCES periode_evaluation(code),
  CONSTRAINT chk_note_globale CHECK (note_globale BETWEEN 0 AND 20),
  CONSTRAINT uq_eval_stage_periode UNIQUE (stage_id, periode_code)
) ENGINE=InnoDB;

CREATE TABLE evaluation_critere (
  evaluation_id  INT NOT NULL,
  critere_id     INT NOT NULL,
  note           DECIMAL(4,2) NOT NULL,
  commentaire    VARCHAR(255) NULL,
  PRIMARY KEY (evaluation_id, critere_id),
  CONSTRAINT fk_evalcrit_evaluation FOREIGN KEY (evaluation_id)
    REFERENCES evaluation(id) ON DELETE CASCADE,
  CONSTRAINT fk_evalcrit_critere FOREIGN KEY (critere_id) REFERENCES critere(id),
  CONSTRAINT chk_evalcrit_note CHECK (note >= 0)        -- note <= barème : trigger
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Attestation : au plus une par stage, seulement si stage terminé (trigger)
-- ---------------------------------------------------------------------
CREATE TABLE attestation (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  stage_id         INT NOT NULL,
  numero           VARCHAR(20)  NOT NULL,
  date_generation  DATE NOT NULL,
  type             VARCHAR(100) NOT NULL DEFAULT 'Attestation de fin de stage',
  generee_par      INT NULL,
  fichier_path     VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_attestation_stage  UNIQUE (stage_id),
  CONSTRAINT uq_attestation_numero UNIQUE (numero),
  CONSTRAINT fk_attestation_stage FOREIGN KEY (stage_id) REFERENCES stage(id),
  CONSTRAINT fk_attestation_generateur FOREIGN KEY (generee_par) REFERENCES utilisateur(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Historique : journal d'audit (Règle 6). Append-only : UPDATE et DELETE
-- sont bloqués par trigger ; en production, le compte applicatif n'a
-- en plus que INSERT/SELECT sur cette table.
-- (entite, entite_id) est polymorphe : pas de clé étrangère possible,
-- c'est voulu pour un journal qui doit survivre aux données.
-- ---------------------------------------------------------------------
CREATE TABLE historique (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  entite          VARCHAR(50)  NOT NULL,
  entite_id       INT          NOT NULL,
  evenement       VARCHAR(150) NOT NULL,
  description     TEXT NULL,
  donnees_avant   JSON NULL,
  donnees_apres   JSON NULL,
  utilisateur_id  INT NULL,
  ip_adresse      VARCHAR(45) NULL,                     -- IPv4 ou IPv6
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_historique_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE SET NULL,
  INDEX idx_historique_entite (entite, entite_id),
  INDEX idx_historique_date (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notification : alertes internes adressées à un utilisateur (ex. : dépôt d'un
-- livrable signalé à l'encadrant du stage et aux administrateurs).
-- Le message est un texte déjà rédigé : il reste lisible même si les données
-- d'origine changent. Supprimées avec le compte destinataire (CASCADE).
-- ---------------------------------------------------------------------
CREATE TABLE notification (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT NOT NULL,                          -- destinataire
  type            VARCHAR(30)  NOT NULL,                 -- ex. : depot_livrable
  message         VARCHAR(255) NOT NULL,
  lien            VARCHAR(255) NULL,                     -- page à ouvrir (adresse relative interne)
  lu              TINYINT(1) NOT NULL DEFAULT 0,
  date_lecture    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE CASCADE,
  CONSTRAINT chk_notification_lu CHECK (lu IN (0, 1)),
  INDEX idx_notification_dest (utilisateur_id, lu, created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- Vues : remplacent les colonnes redondantes supprimées
-- =====================================================================

-- Stage avec son stagiaire (via la convention) et son encadrant actuel
-- (affectation ouverte) : remplace stage.stagiaire_id et encadrant_actuel_id.
CREATE VIEW v_stage_detail AS
SELECT s.id              AS stage_id,
       s.statut          AS stage_statut,
       s.date_debut,
       s.date_fin,
       c.id              AS convention_id,
       c.reference       AS convention_reference,
       c.sujet,
       st.id             AS stagiaire_id,
       st.nom            AS stagiaire_nom,
       st.prenom         AS stagiaire_prenom,
       st.etablissement,
       a.encadrant_id    AS encadrant_actuel_id,
       CONCAT(u.prenom, ' ', u.nom) AS encadrant_actuel
FROM stage s
JOIN convention c  ON c.id  = s.convention_id
JOIN stagiaire  st ON st.id = c.stagiaire_id
LEFT JOIN affectation a ON a.stage_id = s.id AND a.date_fin IS NULL
LEFT JOIN utilisateur u ON u.id = a.encadrant_id;
