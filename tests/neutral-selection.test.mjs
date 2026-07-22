import assert from "node:assert/strict";
import { createRequire } from "node:module";
const select = createRequire(import.meta.url)("../js/neutral-selection.js");
const input = [
  { id: "past", title: "Past", date: "2026-01-01" },
  { id: "invalid", title: "Invalid", date: "nope" },
  { id: "b", title: "B", date: "2026-08-02", score: 2 },
  { id: "a", title: "A", date: "2026-08-01", score: 2 },
  { id: "b", title: "Duplicate", date: "2026-08-03", score: 9 },
];
assert.deepEqual(select.selectEvents(input, { today: "2026-07-22", limit: 9 }).map(select.identity), ["a", "b"]);
assert.equal(select.isCurrentEvent({ id: "range", title: "Range", date: "2026-07-01", endDate: "2026-07-22" }, "2026-07-22"), true);
assert.equal(select.localDate("2026-03-28T23:30:00Z", "Europe/Berlin"), "2026-03-29");
assert.equal(select.localDate("2026-10-25T22:30:00Z", "Europe/Berlin"), "2026-10-25");
assert.deepEqual(select.selectTodayEvents([
  { id: "today", title: "Heute", date: "2026-07-22" },
  { id: "future", title: "Später", date: "2026-07-23", score: 99 }
], { today: "2026-07-22" }).map(select.identity), ["today"]);
console.log("neutral selection contract: OK");
