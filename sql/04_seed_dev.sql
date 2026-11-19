-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 04_seed_dev.sql : DONNÉES DE DÉMONSTRATION - DÉVELOPPEMENT UNIQUEMENT
--
-- /!\ Ne jamais exécuter en recette ni en production.
-- /!\ Le mot de passe de démonstration est communiqué hors dépôt ;
--     il ne doit figurer ni ici ni dans le README.
-- Tous les comptes ont doit_changer_mdp = 1 : changement imposé à la
-- première connexion.
-- Le scénario déroule le cycle complet et passe donc par tous les
-- triggers : si ce script s'exécute sans erreur, les règles acceptent
-- bien le cas nominal.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

-- Comptes (hash bcrypt du mot de passe de démonstration)
INSERT INTO utilisateur (id, nom, prenom, email, mot_de_passe, role, telephone, doit_changer_mdp) VALUES
(1, 'Sofalbe Boussoibe', 'Flavien',     'admin@itcorp.co',     '$2y$12$Dcdx3NLiZdcYFdJ6xbDTgeyfpVUtx4XhG1SZokgcEToK2YBho5JEG', 'admin',     '074000001', 1),
(2, 'Harley',            'Dev',         'encadrant@itcorp.co', '$2y$12$Dcdx3NLiZdcYFdJ6xbDTgeyfpVUtx4XhG1SZokgcEToK2YBho5JEG', 'encadrant', '074000002', 1),
(3, 'Guifo Kouam',       'Henri David', 'stagiaire@itcorp.co', '$2y$12$Dcdx3NLiZdcYFdJ6xbDTgeyfpVUtx4XhG1SZokgcEToK2YBho5JEG', 'stagiaire', '074000003', 1),
(4, 'Leila',             'Assistante',  'direction@itcorp.co', '$2y$12$Dcdx3NLiZdcYFdJ6xbDTgeyfpVUtx4XhG1SZokgcEToK2YBho5JEG', 'direction', '074000004', 1);

-- Cycle complet : fiche stagiaire -> candidature acceptée -> convention
-- validée -> stage -> affectation -> livrables -> évaluation -> attestation
INSERT INTO stagiaire (id, utilisateur_id, nom, prenom, sexe, email, telephone, etablissement, filiere, niveau_etude) VALUES
(1, 3, 'Guifo Kouam', 'Henri David', 'M', 'stagiaire@itcorp.co', '074000003', 'IAI Libreville', 'Génie logiciel', 'Licence 3');

INSERT INTO candidature (id, stagiaire_id, nom_complet, sexe, email, telephone, etablissement, filiere, niveau_etude, poste, statut, date_candidature) VALUES
(1, 1,    'Guifo Kouam Henri David', 'M', 'stagiaire@itcorp.co', '074000003', 'IAI Libreville', 'Génie logiciel', 'Licence 3', 'Développeur PHP', 'acceptee',   '2026-05-10'),
(2, NULL, 'Candidat Démo',           'F', 'candidat@exemple.test', NULL,      'USTM',           'Réseaux',        'Master 1',  'Assistant sécurité', 'en_attente', '2026-09-01');

INSERT INTO convention (id, stagiaire_id, reference, type, sujet, date_debut, date_fin, statut) VALUES
(1, 1, 'CONV-2026-001', 'Stage académique', 'Plateforme de gestion des stages', '2026-06-01', '2026-11-30', 'validee');

INSERT INTO stage (id, convention_id, date_debut, date_fin, statut) VALUES
(1, 1, '2026-06-02', '2026-11-28', 'en_cours');

INSERT INTO affectation (stage_id, encadrant_id, date_debut) VALUES
(1, 2, '2026-06-02');

INSERT INTO livrable (stage_id, titre, date_echeance, date_depot) VALUES
(1, 'Cahier des charges',   '2026-06-30', '2026-06-28 17:00:00'),   -- dans les délais
(1, 'Diagramme de classes', '2026-07-31', '2026-08-04 09:30:00'),   -- hors délai (calculé)
(1, 'Rapport de stage',     '2026-11-20', NULL);                    -- pas encore déposé

UPDATE livrable
   SET statut_validation = 'valide', valide_par = 2, date_validation = '2026-07-02 10:00:00'
 WHERE stage_id = 1 AND titre = 'Cahier des charges';

INSERT INTO evaluation (id, stage_id, encadrant_id, periode_code, note_globale, appreciation_generale) VALUES
(1, 1, 2, 'MI_PARCOURS', 14.40,'Bonne progression, modélisation à consolider.');

INSERT INTO evaluation_critere (evaluation_id, critere_id, note) VALUES
(1, 1, 15), (1, 2, 14), (1, 3, 12), (1, 4, 16), (1, 5, 15);

INSERT INTO historique (entite, entite_id, evenement, description, utilisateur_id) VALUES
('candidature', 1, 'acceptation', 'Candidature acceptée, fiche stagiaire créée', 1),
('stage',       1, 'affectation', 'Encadrant 2 affecté au stage',                1);
