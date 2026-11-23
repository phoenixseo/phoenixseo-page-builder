#!/usr/bin/env python3
"""Baut die WordPress.org-Verzeichnis-Assets des PhoenixSEO Page Builders aus der Marke.

Quelle sind ausschliesslich die Markendateien der Agentur-Website
(`phoenixseo-website/src/assets/logos/brand/`), die hier als `source-mark.svg`
und `source-typo.svg` liegen. Es wird nichts neu gezeichnet: das Zeichen, das
Wortmarken-Pfade und die Farben kommen aus diesen Dateien, nur Anordnung,
Groesse und Hintergrund sind gesetzt.

Ausgabe (nach `out/`):
  banner-1544x500.png   Verzeichnis-Banner, gross
  banner-772x250.png    dasselbe Bild auf halber Breite (wp.org liefert es aus)
  icon-256x256.png      Verzeichnis-Icon, transparent
  icon-128x128.png      dasselbe auf halber Breite

Aufruf:  <venv>/bin/python brand/build-assets.py
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

import cairosvg

HERE = Path(__file__).resolve().parent
OUT = HERE / "out"

# Farben aus phoenixseo.de (src/styles/global.css) — nicht erfunden.
CHARCOAL = "#2F3E46"
AMBER = "#E36414"
MUTED = "#D5DADE"
BANNER_W, BANNER_H = 1544, 500


def _parts() -> tuple[list[str], list[str], float, float]:
    """Zerlegt die Lockup-Datei in Zeichen-Elemente, Wortmarken-Pfade und deren Bbox."""
    svg = (HERE / "source-typo.svg").read_text()
    body = svg.split("</defs>", 1)[1]
    els = re.findall(r"<(?:ellipse|path|circle)\b[^>]*>(?:</(?:ellipse|path|circle)>)?", body)
    if len(els) != 13:
        raise SystemExit(f"unerwartete Lockup-Struktur: {len(els)} Elemente statt 13")
    mark, letters = els[:3], els[3:]
    # Bbox der Wortmarke aus den Pfaddaten (nur M/L/Q/C-Koordinaten, keine Kurvenscheitel)
    xs: list[float] = []
    ys: list[float] = []
    for p in letters:
        d = re.search(r'd="([^"]+)"', p).group(1)
        for x, y in re.findall(r"(-?\d+\.?\d*)\s+(-?\d+\.?\d*)", d):
            xs.append(float(x))
            ys.append(float(y))
    return mark, letters, max(xs) - min(xs), max(ys) - min(ys)


def _mark_group(
    x: float,
    y: float,
    size: float,
    blue: str = "#4286F5",
    white: str = "#FFFFFF",
    halo: bool = True,
) -> str:
    """Zeichen als eigene Gruppe, skaliert auf `size` Kantenlaenge, linke obere Ecke (x, y).

    `halo=False` laesst den weissen Grundkreis weg und stanzt die Aussparung in der
    Hintergrundfarbe aus — fuer das grosse, blasse Wasserzeichen, das sonst einen
    hellen Rand bekommt und wie ein Fleck wirkt.
    """
    mark, _, _, _ = _parts()
    s = size / 54.0
    if halo:
        body = "".join(mark).replace("url(#color-1)", blue).replace("url(#color-2)", white)
    else:
        body = "".join(mark[1:]).replace("url(#color-1)", blue).replace("url(#color-2)", CHARCOAL)
    return f'<g transform="translate({x:.2f} {y:.2f}) scale({s:.6f})">{body}</g>'


def _wordmark_group(x: float, y: float, cap: float, colour: str) -> tuple[str, float]:
    """Wortmarken-Pfade, Cap-Hoehe `cap`, linker oberer Rand der Versalien bei (x, y)."""
    _, letters, w, h = _parts()
    s = cap / h
    # Quellkoordinaten: links 63.8 / oben 14.4 relativ zum 266.725x54-Viewbox
    dx = 63.805
    dy = 14.404
    paths = "".join(
        re.sub(r'fill: *url\(#color-1\);', f"fill: {colour};", p) for p in letters
    )
    g = (
        f'<g transform="translate({x:.2f} {y:.2f}) scale({s:.6f}) translate({-dx:.3f} {-dy:.3f})">'
        + paths
        + "</g>"
    )
    return g, w * s


def banner_svg() -> str:
    left = 112.0
    mark_size = 128.0
    gap = 34.0
    txt_x = left + mark_size + gap
    cap = 54.0
    cap_top = 188.0
    mark_top = cap_top + (cap - mark_size) / 2
    wm, wm_w = _wordmark_group(txt_x, cap_top, cap, "#FFFFFF")
    sub_size = 48.0
    sub_baseline = cap_top + cap + 26 + sub_size * 0.72
    tag_size = 24.0
    tag_baseline = sub_baseline + 32 + tag_size * 0.72
    bar_top = mark_top
    bar_h = tag_baseline - bar_top + 8
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="{BANNER_W}" height="{BANNER_H}" viewBox="0 0 {BANNER_W} {BANNER_H}">
  <rect width="{BANNER_W}" height="{BANNER_H}" fill="{CHARCOAL}"/>
  <g opacity="0.16">{_mark_group(1010, -140, 780, halo=False)}</g>
  <rect x="{left - 36}" y="{bar_top:.1f}" width="4" height="{bar_h:.1f}" fill="{AMBER}"/>
{_mark_group(left, mark_top, mark_size)}
{wm}
  <text x="{txt_x}" y="{sub_baseline:.1f}" font-family="Inter" font-size="{sub_size}" font-weight="600" letter-spacing="1.5" fill="#FFFFFF">Page Builder</text>
  <text x="{txt_x}" y="{tag_baseline:.1f}" font-family="Inter" font-size="{tag_size}" fill="{MUTED}">Reusable page components as native blocks.</text>
</svg>
"""


def icon_svg() -> str:
    size = 256.0
    pad = size * 0.045
    return f"""<svg xmlns="http://www.w3.org/2000/svg" width="{size:.0f}" height="{size:.0f}" viewBox="0 0 {size:.0f} {size:.0f}">
{_mark_group(pad, pad, size - 2 * pad)}
</svg>
"""


def main() -> int:
    OUT.mkdir(exist_ok=True)
    banner = banner_svg()
    icon = icon_svg()
    (OUT / "banner.svg").write_text(banner)
    (OUT / "icon.svg").write_text(icon)

    jobs = [
        ("banner.svg", "banner-1544x500.png", BANNER_W),
        ("banner.svg", "banner-772x250.png", BANNER_W // 2),
        ("icon.svg", "icon-256x256.png", 256),
        ("icon.svg", "icon-128x128.png", 128),
    ]
    for src, dst, width in jobs:
        cairosvg.svg2png(url=str(OUT / src), write_to=str(OUT / dst), output_width=width)
        print(f"{dst:24s} {width}px")

    from PIL import Image

    for name in (j[1] for j in jobs):
        im = Image.open(OUT / name)
        print(f"  {name}: {im.size[0]}x{im.size[1]} {im.mode}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
