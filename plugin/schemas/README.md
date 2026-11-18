# Beispiel-Komponenten

Zehn Komponenten, die mitgeliefert werden, um zu zeigen, was dieser Weg kann.
Jede deckt eine andere Fähigkeit ab; zusammen sind sie ein Lehrgang durch das
Format.

| Typ | Zeigt |
|---|---|
| `hero` | Das Minimum: nur `definition.json`, kein eigener Code. |
| `faq` | Repeater mit zwei Unterspalten, Ausgabe über das generische Markup. |
| `feature-grid` | Ein `select`-Feld als Symbolauswahl, das erst die Ansicht übersetzt. |
| `testimonials` | Zitat-Semantik und ein horizontaler Schwung ohne JavaScript. |
| `pricing-table` | Repeater in einem Repeater, Plus hervorgehobener Tarif. |
| `stats` | Eine Definitionsliste statt lose Zahlen. |
| `cta-band` | Theming über ein `select`-Feld und CSS-Variablen. |
| `vinyl` | Zwei Werte, die niemand eingibt: Tracknummer aus der Reihenfolge, Gesamtspielzeit gerechnet — und nur bei vollständigen Längen. |
| `movie` | `<dl>` für die Fakten, `<meter>` für die Bewertung, `:has()` für das Poster: ein Markup für beide Fälle. |
| `series` | `<details>` pro Staffel statt 200 Zeilen, Nummern aus verschachtelten `<ol>`, Pluralisierung über `_n()`. |

## Drei Dateien, ein Ordner

```
schemas/<typ>/
    definition.json    Pflicht — Felder, Titel, Symbol
    render.php         optional — Markup
    component.css      optional — Gestaltung
```

Der Ordnername **ist** der Typ: er steht im Feldgruppen-Key
(`group_sdcpb_component_<typ>`), im Blocknamen (`sdcpb/<typ>`) und im Pfad. Deshalb
nur Kleinbuchstaben, Ziffern und **Bindestriche** — ein Unterstrich ist als
Komponententyp gültig, aber kein Blockname, und WordPress lehnt
`sdcpb/feature_grid` ab. Ein Bindestrich ist an allen drei Stellen erlaubt:
`feature-grid`, `group_sdcpb_component_feature-grid`, `sdcpb/feature-grid`.

`render.php` bekommt drei lokale Variablen: `$type`, `$data` (Rohwerte) und
`$component` (der Eintrag aus dem Seitendokument). Es gibt sein Markup selbst
aus, inklusive umschließendem Element.

Diese Dateien liegen im Plugin und werden bei jedem Update ersetzt. Wer eine
davon anpassen will, kopiert sie nach `wp-content/sdc-components/<typ>/` — dort
gewinnt sie gegen die Plugin-Version, und dort ist sie im Backend bearbeitbar.

## Die Helfer in `render.php`

Alle geben aus und alle maskieren. `sdcpb_value()` ist der Weg an die Rohwerte
vorbei.

| Helfer | Zweck |
|---|---|
| `sdcpb_text( 'feld' )` | Wert als Text |
| `sdcpb_html( 'feld' )` | Wert als HTML (`wp_kses_post`) |
| `sdcpb_attr( 'feld' )`, `sdcpb_url( 'feld' )` | Wert in einem Attribut |
| `sdcpb_has( 'feld' )` | Hat das Feld einen Wert? `'0'` zählt als Wert. |
| `sdcpb_link( 'feld', 'klasse' )` | `<a>` aus einem Link-Feld |
| `sdcpb_image( 'feld', 'gross' )` | `<img>` samt `alt`, `width`, `height` |
| `sdcpb_image_style( 'feld' )` | `style="--sdc-image:url(…)"` für Hintergründe |
| `sdcpb_each( 'liste', function ( $row ) { … } )` | Zeilen eines Repeaters |
| `sdcpb_fields()` | Das generische Markup, wenn nur der Rahmen eigen sein soll |

Innerhalb von `sdcpb_each()` liest jeder Helfer die Komponente, nicht die Zeile.
Die Zeile muss als letztes Argument mit: `sdcpb_text( 'name', $row )`.

## Wann `definition.json` reicht

Ohne `render.php` gibt das Plugin die Felder generisch aus: jedes Feld als
beschrifteter Block, Listen als Zeilen. Das ist brauchbar für FAQ, Adressen und
Tabellen — überall dort, wo die Reihenfolge der Felder schon die Aussage ist.

Eigenes Markup lohnt sich, sobald eine dieser Bedingungen zutrifft:

- Das Element hat eine Bedeutung, die Felder nicht ausdrücken können
  (`<blockquote>`, `<dl>`, `<figure>`).
- Ein Feldwert ist ein Schlüssel, der erst übersetzt werden muss (Symbolname,
  Farbton).
- Die Anzahl der Einträge entscheidet über das Layout (ein Tarif oder fünf).
- Eine Regel gilt über mehrere Zeilen hinweg (genau ein hervorgehobener Tarif).

## Gestaltung

`component.css` wird automatisch geladen, sobald die Komponente auf der Seite
steht — nichts zu registrieren. Es wird in den `<head>` eingebettet, nicht als
eigene Datei verlinkt. Wer getrennte Dateien will, hängt
`sdcpb_inline_component_styles` auf `false`.

Die Beispiele arbeiten mit CSS-Variablen und `color-mix()`, damit sie in jedem
Theme lesbar bleiben und sich mit einer Zeile überschreiben lassen:

```css
.sdc-feature-grid { --sdc-fg-min: 18rem; }
.sdc-cta { --sdc-cta-bg: #0f766e; --sdc-cta-fg: #ffffff; }
```
