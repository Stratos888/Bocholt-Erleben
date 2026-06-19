-- === BEGIN FILE: api/sql/007_activity_opening_json.sql | Zweck: ergaenzt strukturierte Oeffnungszeiten fuer Aktivitaetspraesenz-Submissions; Umfang: einmalige additive Datenbankmigration ===

ALTER TABLE submissions
  ADD COLUMN activity_opening_json JSON NULL AFTER notes_text;

-- === END FILE: api/sql/007_activity_opening_json.sql ===
