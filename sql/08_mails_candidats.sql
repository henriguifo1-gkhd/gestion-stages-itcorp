-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 08_mails_candidats.sql : MIGRATION pour une base déjà installée
-- Mémorise la date du dernier mail envoyé au candidat depuis l'application
-- (le détail de chaque envoi est tracé dans la table historique).
-- À exécuter UNE SEULE FOIS :
--     mysql -u root -p < sql/08_mails_candidats.sql
-- Une installation neuve n'en a pas besoin : 01_schema.sql contient déjà la colonne.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

ALTER TABLE candidature
  ADD COLUMN dernier_mail_le DATETIME NULL AFTER date_candidature;
