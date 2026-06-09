# Activity Visual Prompt Kit

Stand: 2026-06-09
Status: v0.1 vor Anchor-Test
Scope: exklusive Premium-Activity-Bilder für `Bocholt erleben`

## 1. Grundprinzip

Dieses Prompt-Kit dient dazu, auch in späteren Chats konsistente Activity-Bilder zu erzeugen.

Jede Activity erhält ein eigenes exklusives Hauptbild. Das Bild darf nicht im Event-Visual-System und nicht bei anderen Activities verwendet werden.

KI-Bilder sind standardmäßig symbolisch-activity-spezifisch: Sie sollen die Activity wiedererkennbar machen, ohne eine riskante dokumentarische Kopie eines echten Ortes oder fremder Bildquellen zu erzeugen.

## 2. Standard-Aufbau

Jeder finale Activity-Prompt besteht aus:

1. Execution-Regel
2. Projekt- und Activity-Kontext
3. Exklusivitätsregel
4. Rechtlich sauberer Wiedererkennbarkeitsregel
5. Global Style
6. Activity-Brief
7. Motivmodul
8. Komposition
9. Personenregeln
10. harte Negativregeln
11. Output-spezifische Varianten

## 3. Wiederverwendbarer Basis-Prompt

```text
Create exactly 4 separate standalone final image results.

Important execution rule for ChatGPT:
This is a multi-image generation request.
You must generate 4 separate images in one batch using 4 independent image-generation requests.
Use one request per output.
Do not merge outputs.
Do not create a collage.
Do not create a grid.
Do not create a contact sheet.
Do not create a moodboard.
Do not create a multi-panel layout.
Do not stop after Output 1.

Project:
“Bocholt erleben” PWA activity-card visuals.

Activity:
[ACTIVITY_SLUG]

Exclusive asset rule:
Generate premium image candidates for this single activity only. These images are not event visuals and must not look like reusable generic event images. The final selected image will be exclusive to this activity and must not be reused for any other activity or event.

Legal and factual safety:
The image should make the activity recognizable through its activity type, mood, materials, landscape or urban character, and plausible usage context. It must not claim to be a documentary photograph of an exact real place unless explicitly based on owned or cleared reference material. Do not copy a real photo composition. Do not recreate protected artwork, logos, brand identities, posters, readable signage, or distinctive third-party visual material. The result should be legally clean, symbolic where necessary, and locally plausible rather than a fake exact-location depiction.

Global style:
Realistic local editorial photography. Natural, believable, modest, premium, calm, warm, slightly modern, and visually strong without looking glossy, commercial, or stock-photo-like. It should feel like a high-quality regional lifestyle/editorial photo taken by a good local photographer, not like tourism advertising and not like a perfect AI render.

Local plausibility:
The image should feel plausible for Bocholt, Westmünsterland, the Lower Rhine, or the German-Dutch border region, depending on the activity. Avoid iconic false landmark claims. Use regional plausibility, everyday materials, natural imperfections, and credible small-city or landscape atmosphere.

Activity brief:
[ACTIVITY_SPECIFIC_BRIEF]

Motif module:
[MOTIF_MODULE]

Composition:
Clear 16:9 landscape composition for a PWA card. The main subject must read well in a card context on mobile and desktop. Strong foreground-midground-background structure. Avoid clutter. Avoid extreme close-ups unless the activity specifically requires it. Leave enough calm visual space around the main subject.

People:
People may appear only if they support the activity context. No recognizable children. Faces should not dominate the frame. Avoid cloned people, repeated clothing, unnatural hands, awkward anatomy, or staged stock-photo posing. Prefer backs, side views, distance, motion, or partial silhouettes.

Hard negative rules:
No readable brand logos.
No readable shop signs as a focal element.
No posters or advertising clutter.
No protected artwork as a focal element.
No exact copy of a real photo.
No fake documentary claim of a specific real location.
No recognizable children.
No dominant identifiable faces.
No glossy tourism-poster look.
No sterile AI-render look.
No exaggerated symmetry.
No blank fake displays or fake text panels.
No visible AI artifacts.
No collage or multiple scenes in one image.

Output-specific scene directions:

Output 1:
[VARIANT_1]

Output 2:
[VARIANT_2]

Output 3:
[VARIANT_3]

Output 4:
[VARIANT_4]
```

## 4. Anchor-Test-Prompt: `bocholter-innenstadt-erleben`

Dieser Prompt ist der erste Testkandidat. Er ist noch nicht als v1.0 eingefroren.

```text
Create exactly 4 separate standalone final image results.

Important execution rule for ChatGPT:
This is a multi-image generation request.
You must generate 4 separate images in one batch using 4 independent image-generation requests.
Use one request per output.
Do not merge outputs.
Do not create a collage.
Do not create a grid.
Do not create a contact sheet.
Do not create a moodboard.
Do not create a multi-panel layout.
Do not stop after Output 1.

Project:
“Bocholt erleben” PWA activity-card visuals.

Activity:
bocholter-innenstadt-erleben

Exclusive asset rule:
Generate premium image candidates for this single activity only. These images are not event visuals and must not look like reusable generic event images. The final selected image will be exclusive to this activity and must not be reused for any other activity or event.

Legal and factual safety:
The image should make the activity recognizable through a small-city center, strolling, café/shopping atmosphere, paving, greenery, urban rhythm, and relaxed everyday use. It must not claim to be a documentary photograph of the exact Bocholt city center. Do not copy a real Bocholt photo composition. Do not recreate specific shopfronts, logos, brand identities, readable signs, posters, protected artwork, or distinctive third-party visual material. The result should be legally clean, locally plausible, and activity-specific without being a fake exact-location depiction.

Global style:
Realistic local editorial photography. Natural, believable, modest, premium, calm, warm, slightly modern, and visually strong without looking glossy, commercial, or stock-photo-like. It should feel like a high-quality regional lifestyle/editorial photo taken by a good local photographer, not like tourism advertising and not like a perfect AI render.

Local plausibility:
The image should feel plausible for a medium-sized town center in Bocholt / Westmünsterland / the German-Dutch border region. Do not claim or recreate a specific landmark exactly. The scene should feel regionally believable rather than iconic.

Activity brief:
The activity is about experiencing the town center: strolling, browsing, sitting near cafés, moving through a pleasant pedestrian area, and discovering a relaxed local urban atmosphere. The image should feel like a high-quality hero card for a local discovery app.

Motif module:
Small-city pedestrian center with attractive paving, modest storefront rhythm, subtle café or seating presence, greenery, a few adults moving naturally, and a calm but lively local atmosphere. Keep all signs unreadable or visually secondary.

Composition:
Clear 16:9 landscape composition for a PWA card. The main subject must read well in a card context on mobile and desktop. Strong foreground-midground-background structure. Avoid clutter. Leave enough calm visual space around the main subject.

People:
Show a natural everyday atmosphere with a few adults or mixed-age non-child pedestrians. No recognizable children. Faces should not dominate the frame. No cloned people. Clothing should vary naturally. People should support the scene, not overwhelm it.

Hard negative rules:
No readable brand logos.
No readable shop signs as a focal element.
No posters or advertising clutter.
No protected artwork as a focal element.
No exact copy of a real Bocholt photo.
No fake documentary claim of the exact Bocholt city center.
No recognizable children.
No dominant identifiable faces.
No glossy tourism-poster look.
No sterile AI-render look.
No exaggerated symmetry.
No blank storefronts.
No visible AI artifacts.
No collage or multiple scenes in one image.

Output-specific scene directions:

Output 1:
Pedestrian street scene with attractive paving, a few adults strolling, subtle café presence, greenery, and a pleasant small-town atmosphere.

Output 2:
A town-square-like urban scene with seating, planters or trees, and a calm local city-center feeling, without looking like a tourist postcard.

Output 3:
A more intimate shopping-street view with storefront rhythm, people movement, warm everyday light, and a cozy local discovery mood.

Output 4:
A slightly wider lifestyle city-center scene with urban quality, subtle activity, and strong premium card readability.
```

## 5. Review-Rubrik nach der Generierung

Jedes Ergebnis wird mit diesen Fragen bewertet:

- Passt das Bild eindeutig zur Activity?
- Wird die Activity wiedererkennbar, ohne eine falsche dokumentarische Ortsbehauptung zu machen?
- Ist das Bild rechtlich sauber und frei von Marken-/Logo-/Text-/Kunstproblemen?
- Wirkt es lokal plausibel statt generisch oder fake-lokal?
- Hat es Premium-Editorial-Qualität ohne Stock-/Werbe-/KI-Glätte?
- Funktioniert es als 16:9-Card auf Mobile und Desktop?
- Gibt es erkennbare Kinder, dominante identifizierbare Gesichter oder geklonte Personen?
- Gibt es sichtbare KI-Artefakte?
- Ist das Motiv exklusiv genug für genau diese Activity?
- Würde es die Today/Home-Fläche sichtbar aufwerten?

Freigabe nur bei klarer `ready`-Tendenz. Bei Zweifel: nicht verwenden, Prompt schärfen.

## 6. Prompt-Kit-Freeze

Dieses Prompt-Kit darf erst als `v1.0` gelten, wenn mindestens zwei Anchor-Activities erfolgreich getestet wurden:

- `bocholter-innenstadt-erleben`
- `stadtwald-bocholt-erleben`

Bis dahin bleibt der Status `v0.1 vor Anchor-Test`.
