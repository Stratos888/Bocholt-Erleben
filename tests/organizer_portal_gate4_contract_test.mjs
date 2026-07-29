import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const dashboard = read('fuer-veranstalter/dashboard/index.html');
const script = read('js/organizer-portal-gate4.js');
const endpoint = read('api/organizer-portal/pilot.php');

const required = [
  [dashboard, 'organizer-dashboard-startpartner-card'],
  [dashboard, '/js/organizer-portal-gate4.js'],
  [script, '/api/organizer-portal/pilot.php'],
  [script, 'Kostenloser Startpartner-Pilot'],
  [script, 'Keine automatische kostenpflichtige Verlängerung'],
  [endpoint, 'be_organizer_portal_session'],
  [endpoint, 'startpartner_pilot_entitlements'],
  [endpoint, 'startpartner_pilot_activation_records'],
];
for (const [text, token] of required) {
  if (!text.includes(token)) throw new Error(`Missing organizer Gate-4 contract token: ${token}`);
}
for (const token of ['create-billing-portal-session.php', 'stripe_checkout', 'be_send_mail(']) {
  if (script.includes(token) || endpoint.includes(token)) throw new Error(`Forbidden organizer Gate-4 token: ${token}`);
}
console.log('=== Organizer Portal Gate-4 Contract: OK ===');
