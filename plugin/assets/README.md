# Screenshots

`screenshot-1.png`, `-2.png` and `-3.png` sit in the **plugin root** (wp.org
reads them from there, not from `assets/`) and are listed in `readme.txt` under
`== Screenshots ==`. The numbers are positional: `screenshot-<n>.png` belongs to
the n-th caption, so reordering the list means renaming the files.

| File | Component | Size |
|---|---|---|
| `screenshot-1.png` | Schallplatte (`vinyl`) | 1520×1634 |
| `screenshot-2.png` | Film (`movie`) | 1520×1452 |
| `screenshot-3.png` | Serie (`series`) | 1520×1322 |

## Why these three

They are the components with enough substance to show what the format does: a
track number and a total running time that are computed rather than typed, a
`<meter>` rating next to a definition list, seasons folded into `<details>`
without JavaScript. The generic components (hero, FAQ, feature grid) render
cleanly, but a screenshot of one only proves that a heading can be centred —
which is why they are not in the readme.

## How they were made

Not mockups. The page is built with the plugin, rendered by the theme, captured
from a real browser:

1. `test-scf/seed-examples-page.php` creates the page *SDC Beispiele* with all
   ten components. Inside the wp-env CLI container:
   `wp eval-file /tmp/seed-examples-page.php --allow-root`.
2. `test-scf/make-preview-art.py` draws the three placeholder covers and posters
   into `preview/img/`; they are copied to
   `wp-content/uploads/sdc-demo/{vinyl,movie,series}.jpg`. The art is generated
   from the same facts the demo data holds, because a hand-made file once said
   "8 Staffeln" next to a component that counted three — a mismatch that would
   have shipped in a readme screenshot.
3. The capture is a browser at 1280 px width with `deviceScaleFactor` 2 (so the
   files are 2560 px wide before trimming), clipped to the component's own
   bounding box plus 70 px of side air. The component has to be scrolled into
   view and given a moment first: the images are `loading="lazy"` and stay grey
   when the clip is taken without the page having scrolled there.

## Re-recording

Re-run steps 1–3 after changing a component's markup or CSS, then check the
build: `test-scf/check-readme.sh` and `test-scf/build-zip.py` both fail when the
number of captions and the number of files disagree, or when the numbering has
a gap.

Keep personal data (user names, real client content) out of the frame. The demo
content is invented except for the record and the film, which use public facts
about existing works; the series is fictional on purpose.
