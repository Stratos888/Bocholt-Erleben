#!/usr/bin/env node
import fs from "node:fs";
import path from "node:path";
import { createRequire } from "node:module";

const require = createRequire(import.meta.url);
const selection = require("../js/neutral-selection.js");
const root = process.env.STATIC_RENDER_ROOT
  ? path.resolve(process.env.STATIC_RENDER_ROOT)
  : path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const read = (file) => JSON.parse(fs.readFileSync(path.join(root, file), "utf8"));
const esc = (value) => String(value ?? "").replace(/[&<>\"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
const replace = (file, marker, html) => {
  const target = path.join(root, file);
  const source = fs.readFileSync(target, "utf8");
  const start = `<!-- STATIC:${marker}:START -->`;
  const end = `<!-- STATIC:${marker}:END -->`;
  if (!source.includes(start) || !source.includes(end)) throw new Error(`Missing static marker ${marker} in ${file}`);
  fs.writeFileSync(target, source.replace(new RegExp(`${start}[\\s\\S]*?${end}`), `${start}\n${html}\n${end}`));
};
const detail = (event) => `/events/${encodeURIComponent(event.id)}/`;
const eventCards = (events) => events.map((event) => `<article class="event-card static-content-card" data-item-id="${esc(event.id)}"><h3><a href="${detail(event)}">${esc(event.title)}</a></h3><p>${esc(event.date)} · ${esc(event.location || event.city || "Bocholt")}</p></article>`).join("\n");
const activityCards = (items) => items.map((item) => `<article class="event-card activity-card--rich static-content-card" data-item-id="${esc(item.id)}"><h3>${esc(item.title || item.name)}</h3><p>${esc(item.description || item.short_description || item.category || "Freizeitidee in Bocholt")}</p></article>`).join("\n");

const rawEvents = read("data/events.json");
const events = selection.selectEvents(rawEvents, { now: process.env.STATIC_RENDER_NOW, timeZone: "Europe/Berlin", limit: 8 });
const offerPayload = read("data/offers.json");
const offers = selection.selectActivities(Array.isArray(offerPayload) ? offerPayload : (offerPayload.offers || []), { limit: 6 });
if (!events.length || !offers.length) throw new Error("Static content basis must not be empty");
const todayEvents = selection.selectTodayEvents(rawEvents, { now: process.env.STATIC_RENDER_NOW, timeZone: "Europe/Berlin", limit: 3 });
const homeEvents = todayEvents.length ? todayEvents : events.slice(0, 3);
const homeHeading = todayEvents.length ? "Heute in Bocholt" : "Nächste Termine";
replace("events/index.html", "EVENTS", eventCards(events));
replace("aktivitaeten/index.html", "ACTIVITIES", activityCards(offers));
replace("index.html", "TODAY", `<section data-static-event-context="${todayEvents.length ? "today" : "upcoming"}"><h3>${homeHeading}</h3>${eventCards(homeEvents)}</section>` + activityCards(offers.slice(0, 3)));
