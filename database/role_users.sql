-- Insert default role accounts (password: Password123!)
-- Requires migrations to have added the `role` column.

INSERT INTO users (id, full_name, email, password, role, created_at, updated_at)
VALUES (UUID(), 'Admin User', 'admin@ticketninja.local', '$2y$10$TBIOrh.5N4ar5IToL.KLi.EzWUfUcTVZifzGqQz5pKW24CGDPfkry', 'admin', NOW(), NOW());

INSERT INTO users (id, full_name, email, password, role, created_at, updated_at)
VALUES (UUID(), 'Support Agent', 'agent@ticketninja.local', '$2y$10$TBIOrh.5N4ar5IToL.KLi.EzWUfUcTVZifzGqQz5pKW24CGDPfkry', 'agent', NOW(), NOW());

INSERT INTO users (id, full_name, email, password, role, created_at, updated_at)
VALUES (UUID(), 'Event Organizer', 'organizer@ticketninja.local', '$2y$10$TBIOrh.5N4ar5IToL.KLi.EzWUfUcTVZifzGqQz5pKW24CGDPfkry', 'organizer', NOW(), NOW());

INSERT INTO users (id, full_name, email, password, role, created_at, updated_at)
VALUES (UUID(), 'Attendee User', 'attendee@ticketninja.local', '$2y$10$TBIOrh.5N4ar5IToL.KLi.EzWUfUcTVZifzGqQz5pKW24CGDPfkry', 'attendee', NOW(), NOW());
