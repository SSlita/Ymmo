SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `ymmo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ymmo`;

-- ------------------------------------------------------------
-- Table : agences
-- ------------------------------------------------------------
CREATE TABLE `agences` (
  `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`       VARCHAR(100) NOT NULL,
  `adresse`   VARCHAR(255) NOT NULL,
  `ville`     VARCHAR(100) NOT NULL,
  `cp`        VARCHAR(10)  NOT NULL,
  `telephone` VARCHAR(20)  NOT NULL,
  `email`     VARCHAR(150) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(80)  NOT NULL,
  `prenom`     VARCHAR(80)  NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','agent','client') NOT NULL DEFAULT 'client',
  `agence_id`  INT UNSIGNED NULL,
  `telephone`  VARCHAR(20)  NULL,
  `actif`      TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`agence_id`) REFERENCES `agences`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : biens
-- ------------------------------------------------------------
CREATE TABLE `biens` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `titre`         VARCHAR(200) NOT NULL,
  `description`   TEXT         NOT NULL,
  `type`          ENUM('appartement','maison','bureau','local','terrain','autre') NOT NULL,
  `statut`        ENUM('disponible','vendu','loue','archive') NOT NULL DEFAULT 'disponible',
  `operation`     ENUM('vente','location') NOT NULL DEFAULT 'vente',
  `prix`          DECIMAL(12,2) NOT NULL,
  `surface`       DECIMAL(8,2)  NOT NULL,
  `pieces`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `chambres`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `etage`         TINYINT NULL,
  `adresse`       VARCHAR(255) NOT NULL,
  `ville`         VARCHAR(100) NOT NULL,
  `cp`            VARCHAR(10)  NOT NULL,
  `latitude`      DECIMAL(10,7) NULL,
  `longitude`     DECIMAL(10,7) NULL,
  `agence_id`     INT UNSIGNED NOT NULL,
  `agent_id`      INT UNSIGNED NOT NULL,
  `options`       JSON NULL COMMENT 'Parkings, cave, terrasse, etc.',
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`agence_id`) REFERENCES `agences`(`id`),
  FOREIGN KEY (`agent_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : images_biens
-- ------------------------------------------------------------
CREATE TABLE `images_biens` (
  `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bien_id`  INT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `principale` TINYINT(1) NOT NULL DEFAULT 0,
  `ordre`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (`bien_id`) REFERENCES `biens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : transactions
-- ------------------------------------------------------------
CREATE TABLE `transactions` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bien_id`         INT UNSIGNED NOT NULL,
  `acheteur_id`     INT UNSIGNED NOT NULL,
  `agent_id`        INT UNSIGNED NOT NULL,
  `prix_final`      DECIMAL(12,2) NOT NULL,
  `commission`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `date_transaction` DATE         NOT NULL,
  `type_transaction` ENUM('vente','location') NOT NULL DEFAULT 'vente',
  `notes`           TEXT NULL,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bien_id`)     REFERENCES `biens`(`id`),
  FOREIGN KEY (`acheteur_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`agent_id`)    REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table : demandes (contact client sur un bien)
-- ------------------------------------------------------------
CREATE TABLE `demandes` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bien_id`    INT UNSIGNED NOT NULL,
  `client_id`  INT UNSIGNED NULL,
  `nom`        VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `telephone`  VARCHAR(20)  NULL,
  `message`    TEXT         NOT NULL,
  `statut`     ENUM('nouvelle','traitee','archivee') NOT NULL DEFAULT 'nouvelle',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bien_id`)   REFERENCES `biens`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DONNÉES DE TEST
-- ============================================================

-- Agences (siège + 4 agences exemple)
INSERT INTO `agences` (`nom`, `adresse`, `ville`, `cp`, `telephone`, `email`) VALUES
('Ymmo – Siège Aix-en-Provence', '12 Cours Mirabeau', 'Aix-en-Provence', '13100', '04 42 00 00 00', 'siege@ymmo.fr'),
('Ymmo Lyon', '25 Place Bellecour', 'Lyon', '69002', '04 72 00 00 00', 'lyon@ymmo.fr'),
('Ymmo Paris 8e', '40 Avenue des Champs-Élysées', 'Paris', '75008', '01 40 00 00 00', 'paris@ymmo.fr'),
('Ymmo Marseille', '15 La Canebière', 'Marseille', '13001', '04 91 00 00 00', 'marseille@ymmo.fr'),
('Ymmo Bordeaux', '10 Place de la Bourse', 'Bordeaux', '33000', '05 56 00 00 00', 'bordeaux@ymmo.fr');

-- Biens
INSERT INTO `biens` (`titre`,`description`,`type`,`statut`,`operation`,`prix`,`surface`,`pieces`,`chambres`,`etage`,`adresse`,`ville`,`cp`,`agence_id`,`agent_id`,`options`) VALUES
('Appartement lumineux Cours Mirabeau','Magnifique appartement au cœur d\'Aix-en-Provence, entièrement rénové avec des matériaux haut de gamme. Vue dégagée, parquet massif chêne, cuisine équipée.','appartement','disponible','vente',320000,68,3,2,2,'8 Cours Mirabeau','Aix-en-Provence','13100',1,2,'{"parking":true,"cave":true,"terrasse":false,"ascenseur":true,"gardien":false}'),
('Maison avec piscine Puyricard','Belle villa provençale de 180m² avec piscine, jardin arboré de 800m², double garage. Prestations de standing dans résidence sécurisée.','maison','disponible','vente',750000,180,6,4,NULL,'23 Chemin des Pins','Aix-en-Provence','13540',1,2,'{"parking":true,"cave":true,"terrasse":true,"ascenseur":false,"gardien":true}'),
('Studio idéal investissement','Studio optimisé 25m² proche universités et transports. Parfait pour investissement locatif. Rendement estimé 6%.','appartement','disponible','location',650,25,1,0,3,'15 Rue Paul Bert','Lyon','69006',2,3,'{"parking":false,"cave":false,"terrasse":false,"ascenseur":true,"gardien":false}'),
('Loft moderne Lyon 2e','Exceptionnel loft de 120m² dans ancien entrepôt industriel. Hauteur sous plafond 4m, mezzanine, cuisine ouverte design.','appartement','disponible','vente',480000,120,3,2,1,'4 Rue de la Barre','Lyon','69002',2,3,'{"parking":true,"cave":false,"terrasse":true,"ascenseur":false,"gardien":false}'),
('Bureau prestige Champs-Élysées','Plateau de bureaux 200m² entièrement aménagé, idéal siège social. Adresse premium, accès PMR, salle de réunion, terrasse privative.','bureau','disponible','location',8500,200,NULL,NULL,4,'40 Av. des Champs-Élysées','Paris','75008',3,4,'{"parking":true,"cave":false,"terrasse":true,"ascenseur":true,"gardien":true}'),
('Appartement T4 Vieux-Port','Superbe T4 de 95m² avec vue imprenable sur le Vieux-Port. Parquet, double vitrage, cave. Rare sur le marché.','appartement','disponible','vente',420000,95,4,3,5,'2 Quai du Port','Marseille','13002',4,2,'{"parking":true,"cave":true,"terrasse":false,"ascenseur":true,"gardien":false}'),
('Maison familiale Bordeaux','Maison de ville 130m² sur 3 niveaux, jardin privatif. 4 chambres, garage, proche Chartrons. Très bon état général.','maison','vendu','vente',550000,130,5,4,NULL,'18 Rue Notre-Dame','Bordeaux','33000',5,2,'{"parking":true,"cave":true,"terrasse":true,"ascenseur":false,"gardien":false}'),
('Terrain constructible','Terrain de 1200m² viabilisé, CU positif, dans zone pavillonnaire calme. Idéal pour construction maison individuelle.','terrain','disponible','vente',185000,1200,0,0,NULL,'Chemin des Oliviers','Aix-en-Provence','13590',1,2,'{"parking":false,"cave":false,"terrasse":false,"ascenseur":false,"gardien":false}'),
('T2 meublé quartier étudiant','Appartement T2 de 42m² entièrement meublé et équipé, idéal investisseur ou primo-accédant. Charges incluses.','appartement','disponible','location',890,42,2,1,1,'7 Rue Garibaldi','Lyon','69003',2,3,'{"parking":false,"cave":false,"terrasse":false,"ascenseur":false,"gardien":false}'),
('Penthouse vue panoramique','Penthouse d\'exception 220m² au dernier étage, terrasse 80m² avec vue 360°. Prestations ultra-luxe, domotique intégrée.','appartement','disponible','vente',1850000,220,5,4,8,'100 Boulevard Longchamp','Marseille','13001',4,4,'{"parking":true,"cave":true,"terrasse":true,"ascenseur":true,"gardien":true}');

-- Transactions historiques
INSERT INTO `transactions` (`bien_id`,`acheteur_id`,`agent_id`,`prix_final`,`commission`,`date_transaction`,`type_transaction`) VALUES
(7, 5, 2, 535000, 10700, '2024-03-15', 'vente'),
(3, 6, 3, 650,    195,   '2024-01-10', 'location'),
(9, 5, 3, 890,    267,   '2024-06-01', 'location');

-- Demandes de contact
INSERT INTO `demandes` (`bien_id`,`client_id`,`nom`,`email`,`telephone`,`message`,`statut`) VALUES
(1, 5, 'Thomas Leroy',  'client1@ymmo.fr', '06 02 00 00 01', 'Je suis intéressé par ce bien, pouvez-vous organiser une visite ?', 'nouvelle'),
(2, 6, 'Julie Moreau',  'client2@ymmo.fr', '06 02 00 00 02', 'Ce bien correspond exactement à ce que je recherche. Disponible en semaine.', 'traitee'),
(4, NULL,'Marc Dubois', 'marc.d@email.fr', '06 55 00 00 01', 'Pouvez-vous me donner plus d\''informations sur ce loft ?', 'nouvelle'),
(6, 5, 'Thomas Leroy',  'client1@ymmo.fr', '06 02 00 00 01', 'Vue sur le Vieux-Port très intéressante. Quand peut-on visiter ?', 'nouvelle');

-- Table des offres d'achat/location
CREATE TABLE IF NOT EXISTS `offres` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bien_id`       INT UNSIGNED NOT NULL,
  `client_id`     INT UNSIGNED NOT NULL,
  `agent_id`      INT UNSIGNED NOT NULL,
  `type_offre`    ENUM('achat','location') NOT NULL,
  `prix_propose`  DECIMAL(12,2) NOT NULL,
  `message`       TEXT NULL,
  `statut`        ENUM('en_attente','acceptee','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  `note_agent`    TEXT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`bien_id`)   REFERENCES `biens`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`agent_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des vues de fiches
CREATE TABLE IF NOT EXISTS `vues_biens` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bien_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `ip_hash`    VARCHAR(64) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`bien_id`) REFERENCES `biens`(`id`) ON DELETE CASCADE,
  INDEX idx_bien_date (`bien_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réponses internes aux demandes
CREATE TABLE IF NOT EXISTS `messages_replies` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `demande_id`  INT UNSIGNED NOT NULL,
  `auteur_id`   INT UNSIGNED NOT NULL,
  `message`     TEXT NOT NULL,
  `lu`          TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`demande_id`) REFERENCES `demandes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`auteur_id`)  REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages_replies` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `demande_id`  INT UNSIGNED NOT NULL,
  `auteur_id`   INT UNSIGNED NOT NULL COMMENT 'User qui envoie la réponse',
  `message`     TEXT NOT NULL,
  `lu`          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = lu par le destinataire',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`demande_id`) REFERENCES `demandes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`auteur_id`)  REFERENCES `users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;