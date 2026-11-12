-- Base de données du site Orbite
CREATE DATABASE IF NOT EXISTS orbite
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orbite;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pseudo        VARCHAR(24)  NOT NULL,          -- pseudo tel qu'affiché
  pseudo_key    VARCHAR(24)  NOT NULL UNIQUE,   -- pseudo en minuscules (unicité)
  password_hash VARCHAR(255) NOT NULL,          -- password_hash() de PHP (bcrypt)
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
