/*
   TicketNinja MySQL schema
   This file creates all required tables for the PHP backend.
   Import it via phpMyAdmin or the MySQL CLI.
*/

-- -----------------------------------------------------
-- Database: `ticketninja`
-- -----------------------------------------------------
CREATE DATABASE IF NOT EXISTS `ticketninja` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ticketninja`;

-- -----------------------------------------------------
-- Table `users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `full_name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table `events`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `date` DATE NOT NULL,
  `time` TIME NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100),
  `ticket_price` DECIMAL(10,2) NOT NULL,
  `total_tickets` INT UNSIGNED NOT NULL,
  `available_tickets` INT UNSIGNED NOT NULL,
  `image_url` VARCHAR(500),
  `organizer_id` CHAR(36) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_events_user` FOREIGN KEY (`organizer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table `attendees`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendees` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `event_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `ticket_type` ENUM('VIP','Regular','Student') NOT NULL DEFAULT 'Regular',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_attendees_event` FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attendees_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Table `withdrawals`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `user_id` CHAR(36) NOT NULL,
  `bank_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(30) NOT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_withdrawals_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Indexes for faster look‑ups
-- -----------------------------------------------------
CREATE INDEX idx_events_organizer ON `events`(`organizer_id`);
CREATE INDEX idx_attendees_event ON `attendees`(`event_id`);
CREATE INDEX idx_attendees_user ON `attendees`(`user_id`);
CREATE INDEX idx_withdrawals_user ON `withdrawals`(`user_id`);
