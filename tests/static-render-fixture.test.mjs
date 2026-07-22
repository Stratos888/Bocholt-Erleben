import assert from "node:assert/strict";
import fs from "node:fs";
import os from "node:os";
import path from "node:path";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";

const repo = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const fixture = (events, offers, now = "2026-07-22T10:00:00Z") => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), "seo-static-"));
  fs.mkdirSync(path.join(root, "events")); fs.mkdirSync(path.join(root, "aktivitaeten")); fs.mkdirSync(path.join(root, "data"));
  fs.copyFileSync(path.join(repo, "index.html"), path.join(root, "index.html"));
  fs.copyFileSync(path.join(repo, "events/index.html"), path.join(root, "events/index.html"));
  fs.copyFileSync(path.join(repo, "aktivitaeten/index.html"), path.join(root, "aktivitaeten/index.html"));
  fs.writeFileSync(path.join(root, "data/events.json"), JSON.stringify(events));
  fs.writeFileSync(path.join(root, "data/offers.json"), JSON.stringify({ offers }));
  const run = spawnSync(process.execPath, [path.join(repo, "scripts/render-static-content.mjs")], {
    env: { ...process.env, STATIC_RENDER_ROOT: root, STATIC_RENDER_NOW: now }, encoding: "utf8"
  });
  const read = (file) => fs.readFileSync(path.join(root, file), "utf8");
  return { root, run, home: read("index.html"), events: read("events/index.html"), activities: read("aktivitaeten/index.html") };
};
const activity = { id: "aasee", title: "Aasee erleben", description: "Freizeit am Wasser" };
const today = { id: "today", title: "Heute", date: "2026-07-22", location: "Bocholt" };
const future = { id: "future", title: "Später", date: "2026-07-23", location: "Bocholt", score: 99 };
let result = fixture([future, today], [activity]);
assert.equal(result.run.status, 0, result.run.stderr);
assert.match(result.home, /data-static-event-context="today"/);
assert.match(result.home, /data-item-id="today"/);
assert.doesNotMatch(result.home, /data-item-id="future"/);
assert.match(result.events, /href="\/events\/future\/"/);
assert.match(result.activities, /Aasee erleben/);
result = fixture([future], [activity]);
assert.equal(result.run.status, 0, result.run.stderr);
assert.match(result.home, /Nächste Termine/);
assert.match(result.home, /data-static-event-context="upcoming"/);
result = fixture([{ id: "berlin-day", title: "Berliner Tag", date: "2026-03-29", location: "Bocholt" }], [activity], "2026-03-28T23:30:00Z");
assert.equal(result.run.status, 0, result.run.stderr);
assert.match(result.home, /data-static-event-context="today"/);
for (const [events, offers] of [[[], [activity]], [[future], []]]) {
  result = fixture(events, offers);
  assert.notEqual(result.run.status, 0);
  assert.match(result.run.stderr, /must not be empty/);
}
console.log("static render fixture contract: OK");
