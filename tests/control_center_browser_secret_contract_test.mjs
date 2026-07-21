import fs from 'node:fs';

const read = path => fs.readFileSync(path, 'utf8');
const shared = read('js/control-center/shared.js');
const app = read('js/control-center/app.js');
const legacyInternal = read('intern/index.html');
const legacyWork = read('intern/work.html');
const combined = [shared, app, legacyInternal, legacyWork].join('\n');
const errors = [];

const forbidden = [
  "sessionStorage.getItem('be_cc_password')",
  "sessionStorage.setItem('be_cc_password'",
  'beReviewPassword',
  'localStorage.setItem(storageKey',
  'localStorage.getItem(storageKey)',
];

for (const marker of forbidden) {
  if (combined.includes(marker)) errors.push(`review password must not persist in browser storage: ${marker}`);
}

for (const [name, source] of [
  ['legacy internal dashboard', legacyInternal],
  ['legacy internal work view', legacyWork],
]) {
  if (!source.includes('/steuerzentrale/')) errors.push(`${name} must redirect to the canonical control center`);
  if (source.includes('X-BE-Review-Password')) errors.push(`${name} must not contain its own password transport`);
}

if (!shared.includes("password: ''")) errors.push('review password must start empty in page memory');
if (!shared.includes("credentials:'same-origin'")) errors.push('control-center API calls must keep same-origin session credentials');
if (!app.includes("els.password.value=''")) errors.push('login input must be cleared after authentication and logout');
if (app.includes("if(state.password){")) errors.push('control center must not auto-login from a persisted password');

if (errors.length) {
  console.error('=== Control Center Browser Secret Contract: FAILED ===');
  for (const error of errors) console.error('-', error);
  process.exit(1);
}

console.log('=== Control Center Browser Secret Contract: OK ===');
