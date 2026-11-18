#!/usr/bin/env python3
"""Draw the placeholder artwork for the three media components.

The demo page (`test-scf/seed-examples-page.php`) points `cover` and `poster` at
`wp-content/uploads/sdc-demo/<type>.jpg`. Real cover art is copyrighted, so the
files are simple text cards: a dark gradient with the facts that the component
also prints as text. They are generated rather than drawn by hand so the card
and the demo data cannot drift apart — the series card once said "8 Staffeln"
next to a component that counted three, which is exactly the kind of mismatch a
screenshot in the readme would have published.

Usage:  python3 test-scf/make-preview-art.py [--out DIR]
"""

import argparse
import os
import sys

try:
    from PIL import Image, ImageDraw, ImageFont
except ImportError:  # pragma: no cover - environment guard
    sys.exit("Pillow fehlt: pip install pillow")

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), os.pardir))

SERIF = "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf"
SANS = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"

# Every card carries the same facts as the component's fields. `label` is the
# small line on top, `title` the large one, `foot` the small one at the bottom.
CARDS = [
    {
        "file": "vinyl.jpg",
        "size": (900, 900),
        "base": (54, 22, 25),      # warm, like a printed sleeve
        "label": "APPLE · 1969",
        "title": ["Abbey", "Road"],
        "foot": "THE BEATLES",
    },
    {
        "file": "movie.jpg",
        "size": (800, 1200),
        "base": (58, 14, 16),
        "label": "1964 · SATIRE",
        "title": ["Dr.", "Seltsam"],
        "foot": "STANLEY KUBRICK · 95 MINUTEN",
    },
    {
        "file": "series.jpg",
        "size": (800, 1200),
        "base": (14, 47, 53),      # cool, like a night shot
        "label": "NORDFILM · SEIT 2019",
        "title": ["Nord-", "licht"],
        "foot": "3 STAFFELN · 12 FOLGEN",
    },
]


def font(path, size):
    if not os.path.isfile(path):
        sys.exit(f"Schrift fehlt: {path}")
    return ImageFont.truetype(path, size)


def track(draw, xy, text, fnt, fill, spacing):
    """Draw `text` with extra letter spacing — a card looks set, not typed."""
    x, y = xy
    for char in text:
        draw.text((x, y), char, font=fnt, fill=fill)
        x += draw.textlength(char, font=fnt) + spacing


def tracked_width(draw, text, fnt, spacing):
    return sum(draw.textlength(c, font=fnt) + spacing for c in text) - spacing


def gradient(size, base):
    """Vertical gradient from a lifted version of the base colour to near black."""
    w, h = size
    top = tuple(min(255, int(c * 1.55) + 12) for c in base)
    bottom = tuple(max(0, int(c * 0.45)) for c in base)
    img = Image.new("RGB", (w, h))
    draw = ImageDraw.Draw(img)
    for y in range(h):
        t = y / max(1, h - 1)
        draw.line(
            [(0, y), (w, y)],
            fill=tuple(int(top[i] + (bottom[i] - top[i]) * t) for i in range(3)),
        )
    return img


def card(spec, out_dir):
    w, h = spec["size"]
    img = gradient(spec["size"], spec["base"])
    draw = ImageDraw.Draw(img)

    # A hairline frame, inset — the components draw the outer border themselves.
    inset = int(min(w, h) * 0.045)
    draw.rectangle(
        [inset, inset, w - inset, h - inset],
        outline=(255, 255, 255, 40),
        width=max(1, int(min(w, h) * 0.002)),
    )

    label_font = font(SANS, int(min(w, h) * 0.030))
    title_font = font(SERIF, int(min(w, h) * 0.115))
    foot_font = font(SANS, int(min(w, h) * 0.028))
    white = (245, 242, 238)
    muted = (198, 190, 186)

    # Top label, centred inside the frame.
    lw = tracked_width(draw, spec["label"], label_font, 3)
    track(draw, ((w - lw) / 2, inset * 2.2), spec["label"], label_font, muted, 3)

    # Title: one line per entry, block vertically centred.
    line_height = title_font.size * 1.16
    block = line_height * len(spec["title"])
    y = (h - block) / 2 - inset * 0.2
    for line in spec["title"]:
        tw = draw.textlength(line, font=title_font)
        draw.text(((w - tw) / 2, y), line, font=title_font, fill=white)
        y += line_height

    # Foot, centred near the bottom frame.
    fw = tracked_width(draw, spec["foot"], foot_font, 2)
    track(draw, ((w - fw) / 2, h - inset * 2.2 - foot_font.size), spec["foot"], foot_font, muted, 2)

    path = os.path.join(out_dir, spec["file"])
    img.save(path, "JPEG", quality=90, optimize=True)
    return path


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--out", default=os.path.join(ROOT, "preview", "img"))
    args = parser.parse_args()
    os.makedirs(args.out, exist_ok=True)
    for spec in CARDS:
        path = card(spec, args.out)
        print(f"{os.path.basename(path)}: {os.path.getsize(path) // 1024} KB")
    return 0


if __name__ == "__main__":
    sys.exit(main())
