# Verzeichnis-Assets (wp.org)

Die Bilder im öffentlichen Verzeichnis-Eintrag: Banner 1544×500 und 772×250, Icon 256×256 und
128×128. Sie liegen im SVN unter `assets/`, nicht im Plugin-Paket — `trunk/` enthält nur, was Nutzer
installieren.

## Woher die Formen kommen

Nichts ist nachgezeichnet. `source-mark.svg` und `source-typo.svg` sind Kopien der Markendateien aus
`phoenixseo-website/src/assets/logos/brand/`; das Skript schneidet daraus das Zeichen (erste drei
Elemente: weisser Grundkreis, blauer Kreis, weisse Aussparung) und die zehn Wortmarken-Pfade heraus.
Die Farben stehen in `phoenixseo-website/src/styles/global.css`: Charcoal `#2F3E46`, Amber `#E36414`,
Textgrau `#D5DADE`. Das Markenblau `#4286F5` kommt aus der SVG-Datei selbst.

## Bauen

```bash
python3 -m venv /tmp/venv-render && /tmp/venv-render/bin/pip install cairosvg pillow
/tmp/venv-render/bin/python brand/build-assets.py
```

Ausgabe landet in `out/`: die vier PNGs plus `banner.svg` und `icon.svg` als Quelle für Änderungen.
Der Lauf ist reproduzierbar — gleiche Quellen, gleiche Bytes.

## Was am Aufbau bewusst so ist

- **Wortmarke in Weiss, nicht in Markenblau.** Auf Charcoal verschwindet `#4286F5` (3,1:1); Weiss trägt.
  Das Zeichen bleibt blau, weil es die Fläche dafür hat.
- **Die kleine Fassung ist dasselbe SVG auf halber Breite.** Keine zweite Komposition — sonst driften
  die beiden Fassungen auseinander, und wp.org zeigt je nach Fensterbreite mal die eine, mal die andere.
- **Die Sichel rechts ist das Zeichen ohne seinen weissen Grundkreis**, Aussparung im Hintergrundton
  ausgestanzt, `opacity 0.16`. Mit dem Grundkreis bekommt sie auf dunklem Grund einen hellen Rand und
  wirkt wie ein Fleck.
- **Das Icon hat 4,5 % Rand und transparenten Grund.** Das weisse Halo der Markendatei ist auf dem
  hellen Verzeichnisgrund unsichtbar; auf dunklem Grund wäre es ein Ring — deshalb kein eigener
  Hintergrund im Icon.

Die Tagline im Banner („Reusable page components as native blocks.") ist die Kurzbeschreibung aus
`plugin/readme.txt` — sie wird dort gepflegt, hier nur gesetzt.
