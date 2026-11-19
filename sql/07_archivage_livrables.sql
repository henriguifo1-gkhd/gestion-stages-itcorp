-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 07_archivage_livrables.sql : MIGRATION pour une base déjà installée
-- Ajoute l'archivage des livrables : le bouton « Supprimer » ne détruit plus rien,
-- il archive (le livrable disparaît des listes, reste en base et son fichier est
-- conservé ; il se retrouve dans le filtre « Archivés » et peut être restauré).
-- Les livrables déjà déposés sont concernés exactement comme les nouveaux.
-- À exécuter UNE SEULE FOIS (sans danger si relancé) :
--     mysql -u root -p < sql/07_archivage_livrables.sql
-- Une installation neuve n'en a pas besoin : 01_schema.sql contient déjà ces colonnes.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

SET @deja := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'livrable' AND COLUMN_NAME = 'archive');
SET @ddl := IF(@deja = 0,
  'ALTER TABLE livrable
     ADD COLUMN archive TINYINT(1) NOT NULL DEFAULT 0 AFTER fichier_path,
     ADD COLUMN date_archivage DATETIME NULL AFTER archive,
     ADD COLUMN archive_par INT NULL AFTER date_archivage,
     ADD CONSTRAINT fk_livrable_archiveur FOREIGN KEY (archive_par) REFERENCES utilisateur(id),
     ADD CONSTRAINT chk_livrable_archive CHECK (archive IN (0, 1)),
     ADD INDEX idx_livrable_archive (archive)',
  'SELECT ''archivage deja installe'' AS information');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
