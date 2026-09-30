-- Schéma de la base du laboratoire de gestion des utilisateurs.
--
-- Ce script est monté dans le répertoire d’initialisation du service db, donc
-- exécuté automatiquement au premier démarrage d’un volume vide, une fois la
-- base créée par le point d’entrée du conteneur MySQL.
--
-- Deux propriétés sont exigées :
--   1. Idempotence. Le script peut être rejoué autant de fois que nécessaire
--      sans renvoyer d’erreur et sans altérer la structure existante.
--   2. Table vide au départ. Aucune ligne de démonstration n’est insérée ici :
--      un compte de laboratoire se crée par la page d’inscription, ou par la
--      commande scripts/creer-compte-demo.sh, dont le résultat est consigné
--      dans le suivi.
--
-- Aucun secret n’est écrit dans ce fichier, et aucun mot de passe en clair :
-- la colonne password ne peut contenir qu’une empreinte bcrypt, produite par
-- la fonction password_hash de PHP.

-- La base est créée si elle n’existe pas déjà. Le jeu de caractères utf8mb4
-- couvre l’ensemble des caractères Unicode, accents compris.
CREATE DATABASE IF NOT EXISTS gestion_users
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_0900_ai_ci;

USE gestion_users;

-- Les huit colonnes imposées par le cahier des charges, dans l’ordre.
--
-- id          identifiant interne, jamais exposé tel quel à l’utilisateur.
-- nom         nom de famille, 50 caractères au plus.
-- prenom      prénom, 50 caractères au plus.
-- email       adresse de connexion, unique ; 255 caractères pour la marge.
-- password    empreinte bcrypt, 60 caractères utiles sur 60, donc 255 pour
--             rester compatible avec tout algorithme de hachage futur.
-- created_at  instant de création du compte.
-- updated_at  instant de la dernière modification de la ligne, mis à jour
--             automatiquement par le serveur, sans code applicatif.
-- last_login  instant de la dernière connexion, nul tant que le compte n’a
--             jamais été utilisé.
CREATE TABLE IF NOT EXISTS users (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(50)     NOT NULL,
  prenom     VARCHAR(50)     NOT NULL,
  email      VARCHAR(255)    NOT NULL,
  password   VARCHAR(255)    NOT NULL,
  created_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP,
  last_login TIMESTAMP       NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
