-- === BEGIN FILE: api/sql/008_activity_image_json.sql | Zweck: ergaenzt strukturierte Bildmaterial-Angaben fuer Aktivitaetspraesenz-Submissions; Umfang: einmalige additive Datenbankmigration ===

ALTER TABLE submissions
  ADD COLUMN activity_image_json JSON NULL AFTER activity_opening_json;

-- === END FILE: api/sql/008_activity_image_json.sql ===
