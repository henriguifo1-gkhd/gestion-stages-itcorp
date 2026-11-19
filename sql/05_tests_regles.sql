-- =====================================================================
-- IT CORP - Gestion des stages - v2
-- 05_tests_regles.sql : tests négatifs des règles de gestion
-- À lancer sur une base de DEV chargée avec 01 -> 04 :
--     mysql --force -v gestion_stages < 05_tests_regles.sql
-- Chaque instruction DOIT échouer, sauf celles marquées (préparation).
-- Une instruction de test qui passe signale une règle non appliquée.
-- =====================================================================
SET NAMES utf8mb4;
USE gestion_stages;

-- T01 Règle 3 : stage sur une convention non validée
INSERT INTO convention (id, stagiaire_id, reference, type, sujet, date_debut, date_fin, statut)
VALUES (90, 1, 'CONV-TEST-090', 'Stage académique', 'Test', '2027-01-01', '2027-03-31', 'en_attente'); -- (préparation)
INSERT INTO stage (convention_id, date_debut, date_fin) VALUES (90, '2027-01-02', '2027-03-30');

-- T02 Dates du stage hors de la convention
INSERT INTO convention (id, stagiaire_id, reference, type, sujet, date_debut, date_fin, statut)
VALUES (91, 1, 'CONV-TEST-091', 'Stage académique', 'Test', '2027-01-01', '2027-03-31', 'validee'); -- (préparation)
INSERT INTO stage (convention_id, date_debut, date_fin) VALUES (91, '2026-12-15', '2027-03-30');

-- T03 Relation 1-1 : deuxième stage sur la même convention
INSERT INTO stage (convention_id, date_debut, date_fin) VALUES (1, '2026-06-02', '2026-11-28');

-- T04 Convention liée à un stage repassée en attente
UPDATE convention SET statut = 'en_attente' WHERE id = 1;

-- T05 Dates incohérentes (CHECK)
INSERT INTO convention (stagiaire_id, reference, type, sujet, date_debut, date_fin)
VALUES (1, 'CONV-TEST-092', 'Stage académique', 'Test', '2027-05-01', '2027-04-01');

-- T06 Règle 4 : affecter un compte qui n'est pas encadrant
INSERT INTO affectation (stage_id, encadrant_id, date_debut) VALUES (1, 3, '2026-07-01');

-- T07 Règle 4 : deux affectations ouvertes sur le même stage
INSERT INTO utilisateur (id, nom, prenom, email, mot_de_passe, role) VALUES
(90, 'Test', 'Encadrant2', 'enc2@itcorp.test', 'x', 'encadrant'); -- (préparation)
INSERT INTO affectation (stage_id, encadrant_id, date_debut) VALUES (1, 90, '2026-08-01');

-- T08 Règle 4 : réécrire une affectation au lieu de la clore
UPDATE affectation SET encadrant_id = 90 WHERE stage_id = 1;

-- T09 Évaluation par un encadrant jamais affecté au stage
INSERT INTO evaluation (stage_id, encadrant_id, periode_code, note_globale) VALUES (1, 90, 'FINALE', 15);

-- T10 Règle 5 : deuxième évaluation pour la même période
INSERT INTO evaluation (stage_id, encadrant_id, periode_code, note_globale) VALUES (1, 2, 'MI_PARCOURS', 15);

-- T11 Période inexistante (plus de saisie libre)
INSERT INTO evaluation (stage_id, encadrant_id, periode_code, note_globale) VALUES (1, 2, 's1', 15);

-- T12 Note globale hors 0-20
INSERT INTO evaluation (stage_id, encadrant_id, periode_code, note_globale) VALUES (1, 2, 'FINALE', 21);

-- T13 Note de critère au-dessus du barème
UPDATE evaluation_critere SET note = 25 WHERE evaluation_id = 1 AND critere_id = 1;

-- T14 hors_delai n'est plus saisissable
UPDATE livrable SET hors_delai = 0 WHERE titre = 'Diagramme de classes';

-- T15 Livrable validé par un non-encadrant
UPDATE livrable SET statut_validation = 'valide', valide_par = 4, date_validation = NOW()
WHERE titre = 'Diagramme de classes';

-- T16 Livrable validé sans avoir été déposé
UPDATE livrable SET statut_validation = 'valide', valide_par = 2, date_validation = NOW()
WHERE titre = 'Rapport de stage';

-- T17 Attestation pour un stage non terminé
INSERT INTO attestation (stage_id, numero, date_generation) VALUES (1, 'ATT-TEST-1', CURDATE());

-- T18 Compte stagiaire lié à un compte d'un autre rôle
UPDATE stagiaire SET utilisateur_id = 2 WHERE id = 1;

-- T19 Règle 2 : deuxième fiche pour la même personne
INSERT INTO stagiaire (nom, prenom, sexe, email, etablissement)
VALUES ('Guifo Kouam', 'Henri David', 'M', 'stagiaire@itcorp.co', 'IAI Libreville');

-- T20 Lien vers un stagiaire sur une candidature non acceptée
UPDATE candidature SET stagiaire_id = 1 WHERE id = 2;

-- T21 Sexe non renseigné (plus de valeur par défaut)
INSERT INTO candidature (nom_complet, email, etablissement, poste, date_candidature)
VALUES ('Sans Sexe', 'x@exemple.test', 'IAI', 'Dev', CURDATE());
INSERT INTO candidature (nom_complet, sexe, email, etablissement, poste, date_candidature)
VALUES ('Sexe Invalide', 'X', 'y@exemple.test', 'IAI', 'Dev', CURDATE());

-- T22 Règle 6 : modifier le journal d'audit
UPDATE historique SET description = 'falsifié' WHERE id = 1;

-- T23 Règle 6 : supprimer le journal d'audit
DELETE FROM historique WHERE id = 1;

-- T24 Stage attesté repassé en cours (à tester après avoir terminé + attesté le stage)
UPDATE stage SET statut = 'termine' WHERE id = 1;          -- (préparation)
INSERT INTO attestation (stage_id, numero, date_generation) VALUES (1, 'ATT-2026-001', CURDATE()); -- (préparation)
UPDATE stage SET statut = 'en_cours' WHERE id = 1;          -- DOIT échouer
