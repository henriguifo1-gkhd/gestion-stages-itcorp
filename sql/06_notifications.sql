-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 06_notifications.sql : MIGRATION pour une base déjà installée
-- Ajoute la table des notifications (dépôt de livrable signalé à l'encadrant
-- du stage et aux administrateurs). À exécuter UNE SEULE FOIS :
--     mysql -u root -p < sql/06_notifications.sql
-- Sans effet sur les données existantes. Une installation neuve n'en a pas
-- besoin : 01_schema.sql crée déjà cette table.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

CREATE TABLE IF NOT EXISTS notification (
  id              BIGINT AUTO_INCREMENT PRIMARY KEY,
  utilisateur_id  INT NOT NULL,
  type            VARCHAR(30)  NOT NULL,
  message         VARCHAR(255) NOT NULL,
  lien            VARCHAR(255) NULL,
  lu              TINYINT(1) NOT NULL DEFAULT 0,
  date_lecture    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_utilisateur FOREIGN KEY (utilisateur_id)
    REFERENCES utilisateur(id) ON DELETE CASCADE,
  CONSTRAINT chk_notification_lu CHECK (lu IN (0, 1)),
  INDEX idx_notification_dest (utilisateur_id, lu, created_at)
) ENGINE=InnoDB;
