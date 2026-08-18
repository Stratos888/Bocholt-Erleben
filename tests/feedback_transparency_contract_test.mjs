import fs from 'node:fs';

const feedback = fs.readFileSync('js/feedback.js', 'utf8');
const privacy = fs.readFileSync('datenschutz/index.html', 'utf8');

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    process.exit(1);
  }
}

assert(
  feedback.includes('Seite, Filter und Bezug werden automatisch mitgesendet.'),
  'UI-Hinweis muss den tatsächlichen Kontext-Payload beschreiben.'
);
assert(
  !feedback.includes('Seite, Filter, Bezug und Bildschirmbreite werden automatisch mitgesendet.'),
  'UI darf Bildschirmbreite nicht als übermittelt ausweisen.'
);
assert(
  feedback.includes('["source_label", "feedback_type", "route", "viewport", "submitted_at"]'),
  'Datensparsames pruneFormData muss viewport und submitted_at weiterhin entfernen.'
);
assert(
  privacy.includes('Suchbegriff und aktive Filter) zur technischen Entgegennahme und Weiterleitung an den Formular-Dienst'),
  'Datenschutzerklärung muss die tatsächlich übermittelten Kontextangaben nennen.'
);
assert(
  !privacy.includes('Bildschirmbreite und Zeitpunkt der Übermittlung'),
  'Datenschutzerklärung darf Bildschirmbreite/Zeitpunkt nicht als Formspree-Payload ausweisen.'
);
assert(
  privacy.includes('<p>Stand: August 2026</p>'),
  'Stand der aktualisierten Datenschutzerklärung muss August 2026 sein.'
);

console.log('=== Feedback Transparency Contract: OK ===');
