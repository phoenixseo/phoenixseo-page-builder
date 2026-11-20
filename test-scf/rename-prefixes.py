#!/usr/bin/env python3
"""Rename the plugin's prefixes to the 5-character `sdcpb` form.

Run from the repo root. Prints a per-pattern count before writing so the
change can be reviewed instead of trusted.
"""
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent

EXTS = {".php", ".js", ".json", ".md", ".txt", ".yml", ".sh", ".py", ".dist"}
SKIP_DIRS = {".git", "node_modules", "vendor", "wordpress", "_research", "shots", "_archiv", "img"}
SKIP_FILES = {"phoenixseo-page-builder.zip", "K7-RELEASE-BERICHT.md", "rename-prefixes.py"}

# Order matters: the wider patterns run first, the narrow `sdc_` one last so
# that it cannot eat the tail of `sdcpb_`.
RULES = [
    ("SDC_PB_", "SDCPB_"),
    ("Sdc_", "Sdcpb_"),
    ("sdc_pb_", "sdcpb_"),
    ("_sdc_data", "_sdcpb_data"),
    ("_sdc_migrated", "_sdcpb_migrated"),
    ("group_sdc_component_", "group_sdcpb_component_"),
    (r"(?<![A-Za-z0-9_])sdc_", "sdcpb_"),
    ("sdc/", "sdcpb/"),
    ("SDCBlockRows", "SDCPBBlockRows"),
    ("SDCMediaField", "SDCPBMediaField"),
    ("SDC_BLOCK", "SDCPB_BLOCK"),
    ("SDC_FIELD_EDITOR", "SDCPB_FIELD_EDITOR"),
    ("SDC-Block", "SDCPB-Block"),
    ("SDC-Field-Editor", "SDCPB-Field-Editor"),
]


def wanted(path: pathlib.Path) -> bool:
    if path.name in SKIP_FILES or path.name.endswith(".pot"):
        return False
    if any(part in SKIP_DIRS for part in path.relative_to(ROOT).parts):
        return False
    return path.name.endswith(".dist") or path.suffix in EXTS


def main() -> int:
    dry = "--apply" not in sys.argv
    counts = {pattern: 0 for pattern, _ in RULES}
    touched = []

    for path in sorted(ROOT.rglob("*")):
        if not path.is_file() or not wanted(path):
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue

        new = text
        for pattern, repl in RULES:
            hits = len(re.findall(pattern, new))
            if hits:
                counts[pattern] += hits
            new = re.sub(pattern, repl, new)

        if new != text:
            touched.append(str(path.relative_to(ROOT)))
            if not dry:
                path.write_text(new, encoding="utf-8")

    print(f"{'DRY RUN — ' if dry else ''}{len(touched)} Datei(en) betroffen")
    for pattern, n in counts.items():
        if n:
            print(f"  {pattern:24} {n}")
    for name in touched:
        print(f"  · {name}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
