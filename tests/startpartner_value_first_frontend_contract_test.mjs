import fs from 'node:fs';

const gate4 = fs.readFileSync('js/control-center/startpartner-gate4.js', 'utf8');
const errors = [];

if (gate4.includes('!gate4.ready_distribution')) {
  errors.push('preactivation UI still blocks pilot start on ready_distribution');
}
for (const marker of [
  "if(gate4.activation_ready)return {code:'activate',label:'Pilot jetzt starten',action:'activate'};",
  'Optionale Reichweitenkooperation',
  'Nicht vereinbart (optional)',
  'Keine Voraussetzung für Pilotstart, Veröffentlichung oder Fortführung.',
]) {
  if (!gate4.includes(marker)) errors.push(`missing value-first frontend marker: ${marker}`);
}

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}

console.log('Startpartner value-first frontend contract: OK');
