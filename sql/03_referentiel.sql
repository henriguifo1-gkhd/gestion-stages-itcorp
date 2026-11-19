-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 03_referentiel.sql : données de référence (tous environnements)
-- Valeurs PROPOSÉES : à valider avec les encadrants avant mise en prod.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

INSERT INTO periode_evaluation (code, libelle, ordre) VALUES
('MI_PARCOURS', 'Évaluation de mi-parcours', 1),
('FINALE',      'Évaluation finale',         2);

INSERT INTO critere (libelle, bareme) VALUES
('Qualité du travail rendu',        20),
('Autonomie et prise d''initiative', 20),
('Respect des délais',              20),
('Communication et intégration',    20),
('Assiduité et ponctualité',        20);
