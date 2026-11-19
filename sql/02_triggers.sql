-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 02_triggers.sql : règles de gestion appliquées par la base
-- À exécuter après 01_schema.sql, avec le client mysql/mariadb
-- (la directive DELIMITER est interprétée par le client).
--
-- Principe : la base est le dernier rempart. L'application contrôle
-- aussi ces règles (messages clairs pour l'utilisateur), mais aucun
-- chemin (script, import, bug, accès direct) ne peut les contourner.
-- Toutes les erreurs métier remontent en SQLSTATE 45000.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

DELIMITER $$

-- ---------------------------------------------------------------------
-- Contrôle commun : l'utilisateur existe, est actif et a le rôle attendu
-- ---------------------------------------------------------------------
CREATE PROCEDURE p_verif_role(IN p_utilisateur_id INT, IN p_role VARCHAR(20), IN p_contexte VARCHAR(80))
BEGIN
  DECLARE v_role VARCHAR(20);
  DECLARE v_actif TINYINT;
  DECLARE v_msg VARCHAR(255);

  SELECT role, actif INTO v_role, v_actif
  FROM utilisateur WHERE id = p_utilisateur_id;

  IF v_role IS NULL OR v_role <> p_role OR v_actif <> 1 THEN
    SET v_msg = CONCAT(p_contexte, ' : l''utilisateur ', p_utilisateur_id,
                       ' doit être un compte actif de rôle ', p_role);
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Stagiaire : le compte lié doit être un compte de rôle 'stagiaire'
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_stagiaire_bi BEFORE INSERT ON stagiaire FOR EACH ROW
BEGIN
  IF NEW.utilisateur_id IS NOT NULL THEN
    CALL p_verif_role(NEW.utilisateur_id, 'stagiaire', 'Compte du stagiaire');
  END IF;
END$$

CREATE TRIGGER trg_stagiaire_bu BEFORE UPDATE ON stagiaire FOR EACH ROW
BEGIN
  IF NEW.utilisateur_id IS NOT NULL AND NOT (NEW.utilisateur_id <=> OLD.utilisateur_id) THEN
    CALL p_verif_role(NEW.utilisateur_id, 'stagiaire', 'Compte du stagiaire');
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Règle 3 : un stage repose sur une convention VALIDÉE, et ses dates
-- effectives restent dans la période de la convention.
-- ---------------------------------------------------------------------
CREATE PROCEDURE p_verif_stage(IN p_convention_id INT, IN p_debut DATE, IN p_fin DATE,
                               IN p_statuts_admis VARCHAR(100))
BEGIN
  DECLARE v_statut VARCHAR(20);
  DECLARE v_debut DATE;
  DECLARE v_fin DATE;

  SELECT statut, date_debut, date_fin INTO v_statut, v_debut, v_fin
  FROM convention WHERE id = p_convention_id;

  IF v_statut IS NULL OR FIND_IN_SET(v_statut, p_statuts_admis) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'le stage doit être rattaché à une convention validée';
  END IF;

  IF p_debut < v_debut OR p_fin > v_fin THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Les dates du stage doivent être comprises dans celles de la convention';
  END IF;
END$$

CREATE TRIGGER trg_stage_bi BEFORE INSERT ON stage FOR EACH ROW
BEGIN
  CALL p_verif_stage(NEW.convention_id, NEW.date_debut, NEW.date_fin, 'validee');
END$$

CREATE TRIGGER trg_stage_bu BEFORE UPDATE ON stage FOR EACH ROW
BEGIN
  -- en cours de vie, la convention peut être passée à 'terminee'
  IF NOT (NEW.convention_id <=> OLD.convention_id)
     OR NEW.date_debut <> OLD.date_debut OR NEW.date_fin <> OLD.date_fin THEN
    CALL p_verif_stage(NEW.convention_id, NEW.date_debut, NEW.date_fin, 'validee,terminee');
  END IF;
  -- un stage qui a une attestation ne peut plus quitter le statut 'termine'
  IF OLD.statut = 'termine' AND NEW.statut <> 'termine'
     AND EXISTS (SELECT 1 FROM attestation WHERE stage_id = NEW.id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Stage attesté : le statut termine est définitif';
  END IF;
END$$

-- Une convention qui porte un stage ne peut plus revenir en attente ni être rejetée
CREATE TRIGGER trg_convention_bu BEFORE UPDATE ON convention FOR EACH ROW
BEGIN
  IF NEW.statut IN ('en_attente','rejetee') AND NEW.statut <> OLD.statut
     AND EXISTS (SELECT 1 FROM stage WHERE convention_id = NEW.id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'cette convention est déjà liée à un stage, son statut ne peut plus être modifié';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Règle 4 : seul un encadrant actif peut être affecté
-- (l'unicité de l'affectation ouverte est garantie par uq_affectation_une_ouverte)
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_affectation_bi BEFORE INSERT ON affectation FOR EACH ROW
BEGIN
  CALL p_verif_role(NEW.encadrant_id, 'encadrant', 'Affectation');
END$$

CREATE TRIGGER trg_affectation_bu BEFORE UPDATE ON affectation FOR EACH ROW
BEGIN
  IF NEW.encadrant_id <> OLD.encadrant_id OR NEW.stage_id <> OLD.stage_id THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'une affectation existante ne peut pas être modifiée';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Évaluation : rédigée par un encadrant affecté (actuellement ou par le
-- passé) au stage évalué.
-- ---------------------------------------------------------------------
CREATE PROCEDURE p_verif_evaluateur(IN p_stage_id INT, IN p_encadrant_id INT)
BEGIN
  CALL p_verif_role(p_encadrant_id, 'encadrant', 'Évaluation');
  IF NOT EXISTS (SELECT 1 FROM affectation
                 WHERE stage_id = p_stage_id AND encadrant_id = p_encadrant_id) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Évaluation : l''encadrant n''a jamais été affecté à ce stage';
  END IF;
END$$

CREATE TRIGGER trg_evaluation_bi BEFORE INSERT ON evaluation FOR EACH ROW
BEGIN
  CALL p_verif_evaluateur(NEW.stage_id, NEW.encadrant_id);
END$$

CREATE TRIGGER trg_evaluation_bu BEFORE UPDATE ON evaluation FOR EACH ROW
BEGIN
  IF NEW.stage_id <> OLD.stage_id OR NEW.encadrant_id <> OLD.encadrant_id THEN
    CALL p_verif_evaluateur(NEW.stage_id, NEW.encadrant_id);
  END IF;
END$$

-- Note d'un critère plafonnée par le barème du critère
CREATE PROCEDURE p_verif_note_critere(IN p_critere_id INT, IN p_note DECIMAL(4,2))
BEGIN
  IF p_note > (SELECT bareme FROM critere WHERE id = p_critere_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La note dépasse le barème du critère';
  END IF;
END$$

CREATE TRIGGER trg_evalcrit_bi BEFORE INSERT ON evaluation_critere FOR EACH ROW
BEGIN
  CALL p_verif_note_critere(NEW.critere_id, NEW.note);
END$$

CREATE TRIGGER trg_evalcrit_bu BEFORE UPDATE ON evaluation_critere FOR EACH ROW
BEGIN
  CALL p_verif_note_critere(NEW.critere_id, NEW.note);
END$$

-- ---------------------------------------------------------------------
-- Livrable : validé ou rejeté uniquement par un encadrant
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_livrable_bi BEFORE INSERT ON livrable FOR EACH ROW
BEGIN
  IF NEW.valide_par IS NOT NULL THEN
    CALL p_verif_role(NEW.valide_par, 'encadrant', 'Validation du livrable');
  END IF;
END$$

CREATE TRIGGER trg_livrable_bu BEFORE UPDATE ON livrable FOR EACH ROW
BEGIN
  IF NEW.valide_par IS NOT NULL AND NOT (NEW.valide_par <=> OLD.valide_par) THEN
    CALL p_verif_role(NEW.valide_par, 'encadrant', 'Validation du livrable');
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Attestation : uniquement pour un stage terminé
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_attestation_bi BEFORE INSERT ON attestation FOR EACH ROW
BEGIN
  IF (SELECT statut FROM stage WHERE id = NEW.stage_id) <> 'termine' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Attestation : le stage doit être au statut termine';
  END IF;
END$$

CREATE TRIGGER trg_attestation_bu BEFORE UPDATE ON attestation FOR EACH ROW
BEGIN
  IF NEW.stage_id <> OLD.stage_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Attestation : le stage ne peut pas être changé';
  END IF;
END$$

-- ---------------------------------------------------------------------
-- Règle 6 : le journal d'audit est non modifiable
-- ---------------------------------------------------------------------
CREATE TRIGGER trg_historique_bu BEFORE UPDATE ON historique FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique : journal en ajout seul, modification interdite';
END$$

CREATE TRIGGER trg_historique_bd BEFORE DELETE ON historique FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Historique : journal en ajout seul, suppression interdite';
END$$

DELIMITER ;

-- =====================================================================
-- Compte applicatif (exemple, à adapter : hôte, secret hors dépôt)
-- Moindre privilège : aucun droit DDL (CREATE/ALTER/DROP), et le
-- journal historique en INSERT/SELECT seulement.
-- =====================================================================
-- CREATE USER 'app_stages'@'10.0.0.%' IDENTIFIED BY '<secret_hors_depot>';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON gestion_stages.utilisateur        TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON gestion_stages.remember_token     TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON gestion_stages.password_reset     TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.candidature        TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.stagiaire          TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.convention         TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.stage              TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.affectation        TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.livrable           TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE         ON gestion_stages.evaluation         TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON gestion_stages.evaluation_critere TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT                 ON gestion_stages.attestation        TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT, INSERT                 ON gestion_stages.historique         TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT ON gestion_stages.periode_evaluation TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT ON gestion_stages.critere            TO 'app_stages'@'10.0.0.%';
-- GRANT SELECT ON gestion_stages.v_stage_detail     TO 'app_stages'@'10.0.0.%';
