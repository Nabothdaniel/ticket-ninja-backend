-- Update schema for User Preferences and Ticket Verification

-- Add preferences column to users table
ALTER TABLE `users` ADD COLUMN `preferences` JSON DEFAULT NULL;

-- Add ticket_code and checked_in_at to attendees table
-- We use a generated unique code for the ticket
ALTER TABLE `attendees` ADD COLUMN `ticket_code` VARCHAR(64) NOT NULL UNIQUE AFTER `ticket_type`;
ALTER TABLE `attendees` ADD COLUMN `checked_in_at` TIMESTAMP NULL DEFAULT NULL AFTER `ticket_code`;

-- Index for faster ticket lookup
CREATE INDEX idx_attendees_ticket_code ON `attendees`(`ticket_code`);
