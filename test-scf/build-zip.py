#!/usr/bin/env python3
"""Build the wp.org distribution zip for phoenixseo-page-builder.

Reproduces what `zip -r phoenixseo-page-builder.zip plugin/ -x ...` would produce, but
reads the exclusion rules from `.distignore` so the package and the ignore file
cannot drift apart. Exit code is 0 only when the package is verifiably clean.

Usage:  python3 test-scf/build-zip.py [--out PATH]
"""

import argparse
import fnmatch
import os
import sys
import zipfile

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), os.pardir))
PLUGIN = os.path.join(ROOT, "plugin")

# The directory name inside the zip is the plugin's slug on wp.org: WordPress
# installs whatever folder the archive contains, so a package rooted at
# `plugin/` would install as a plugin called "plugin". Kept in sync with the
# main plugin file rather than typed twice.
SLUG = "phoenixseo-page-builder"


def load_rules():
    """Read .distignore into (anchored, unanchored) pattern lists."""
    anchored, bare = [], []
    path = os.path.join(ROOT, ".distignore")
    with open(path, encoding="utf-8") as handle:
        for line in handle:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if line.startswith("/"):
                anchored.append(line[1:])
            else:
                bare.append(line)
    return anchored, bare


def excluded(rel, anchored, bare):
    """Whether a root-relative path is excluded.

    An anchored pattern excludes the path itself and everything below it. A
    bare pattern matches any path component (a directory name at any depth), or
    the path's own basename — so `composer.json` also catches
    `plugin/composer.json`. fnmatch's `*` does not cross `/`.
    """
    for pattern in anchored:
        if rel == pattern or rel.startswith(pattern + "/"):
            return True
    basename = rel.rsplit("/", 1)[-1]
    for pattern in bare:
        if fnmatch.fnmatch(basename, pattern):
            return True
        for part in rel.split("/"):
            if fnmatch.fnmatch(part, pattern):
                return True
    return False


def collect(anchored, bare):
    entries = []
    for dirpath, dirnames, filenames in os.walk(PLUGIN):
        rel_dir = os.path.relpath(dirpath, ROOT).replace(os.sep, "/")
        # Prune excluded directories so we never descend into node_modules.
        dirnames[:] = sorted(
            d for d in dirnames if not excluded(f"{rel_dir}/{d}", anchored, bare)
        )
        for name in sorted(filenames):
            rel = f"{rel_dir}/{name}"
            if excluded(rel, anchored, bare):
                continue
            entries.append(rel)
    return sorted(entries)


# Markup that a source scanner reads as raw output.
#
# The wp.org review tool greps the uploaded package; it cannot tell a string
# that *names* a stylesheet tag apart from code that *prints* one. That is how
# the same "Use wp_enqueue commands" finding came back twice while the plugin
# had been enqueuing through wp_add_inline_style() all along. A better
# explanation in the mail does not change the next scan; not shipping the
# literal does. This gate is what keeps it that way.
MARKUP = ("<style", "</style", "<script", "</script")

TEXT_SUFFIXES = (".php", ".js", ".css", ".txt", ".json", ".md", ".html")


def check_markup(archive_path):
    """Scan the built package for stylesheet or script tags in the source."""
    problems = []
    hits = 0
    with zipfile.ZipFile(archive_path) as archive:
        for name in archive.namelist():
            if not name.lower().endswith(TEXT_SUFFIXES):
                continue
            try:
                text = archive.read(name).decode("utf-8")
            except UnicodeDecodeError:
                continue
            for number, line in enumerate(text.splitlines(), 1):
                if any(needle in line.lower() for needle in MARKUP):
                    hits += 1
                    if hits <= 5:
                        print(f"  markup: {name}:{number} {line.strip()[:90]}")
    print(f"check raw markup: {hits} hit(s)")
    if hits:
        problems.append(f"{hits} raw style/script tag literal(s) in the package")
    return problems


def count_captions(readme_path):
    """Number the `1. `-style captions inside the Screenshots section.

    wp.org maps `screenshot-<n>.png` to the n-th list item, so the count is what
    has to agree, not the wording. A missing section means zero captions.
    """
    try:
        with open(readme_path, encoding="utf-8") as handle:
            lines = handle.read().splitlines()
    except OSError:
        return 0
    inside = False
    count = 0
    for line in lines:
        stripped = line.strip()
        if stripped.startswith("==") and stripped.endswith("=="):
            inside = stripped == "== Screenshots =="
            continue
        if inside and stripped and stripped[0].isdigit() and ". " in stripped[:4]:
            count += 1
    return count


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--out", default=os.path.join(ROOT, "phoenixseo-page-builder.zip"))
    args = parser.parse_args()

    anchored, bare = load_rules()
    entries = collect(anchored, bare)

    main_file = f"plugin/{SLUG}.php"
    if main_file not in entries:
        print(f"No main plugin file {main_file} in the package.", file=sys.stderr)
        return 1

    if os.path.exists(args.out):
        os.remove(args.out)

    with zipfile.ZipFile(args.out, "w", zipfile.ZIP_DEFLATED) as archive:
        for rel in entries:
            # `plugin/x.php` -> `phoenixseo-page-builder/x.php`
            arcname = SLUG + "/" + rel[len("plugin/"):]
            archive.write(os.path.join(ROOT, rel), arcname)

    print(f"out: {args.out}")
    print(f"files: {len(entries)}")
    for rel in entries:
        print(rel)

    # Verify the prohibitions and the required files.
    #
    # `screenshot-*` used to be forbidden here: the readme claimed four shots
    # that did not exist, and a package shipping a caption for a missing file is
    # a review finding. Now the shots exist, so the rule flips instead of being
    # dropped — wp.org reads them from the package root, and a caption without
    # its file is still a finding. The number of files must match the captions
    # in readme.txt, in both directions.
    forbidden = [("node_modules", "node_modules/"), ("vendor", "/vendor/"),
                 ("tests", "/tests/")]
    problems = []
    for label, needle in forbidden:
        if label == "tests":
            hits = [e for e in entries if e.startswith("plugin/tests/")]
        else:
            hits = [e for e in entries if needle in "/" + e]
        print(f"check {label}: {len(hits)} hit(s)")
        if hits:
            problems.append(f"{label}: {hits[:3]}")
    # The required files, looked up by their path inside the package.
    for required in ("LICENSE", "readme.txt", "phoenixseo-page-builder.php"):
        rel = f"plugin/{required}"
        present = rel in entries
        print(f"check {rel}: {'yes' if present else 'MISSING'}")
        if not present:
            problems.append(f"missing {rel}")

    # Screenshots: number them from 1 without gaps, and make the count agree
    # with the captions listed in readme.txt.
    shots = sorted(
        (e for e in entries if e.startswith("plugin/screenshot-")),
        key=lambda e: int(e[len("plugin/screenshot-"):-len(".png")] or 0)
        if e[len("plugin/screenshot-"):-len(".png")].isdigit() else 999,
    )
    numbers = []
    for entry in shots:
        stem = entry[len("plugin/screenshot-"):-len(".png")]
        if not stem.isdigit():
            problems.append(f"screenshot not named screenshot-<n>.png: {entry}")
            continue
        numbers.append(int(stem))
    print(f"check screenshots: {len(shots)} file(s) {numbers}")
    if numbers and numbers != list(range(1, len(numbers) + 1)):
        problems.append(f"screenshot numbering has a gap: {numbers}")

    captions = count_captions(os.path.join(PLUGIN, "readme.txt"))
    print(f"check captions: {captions} in readme.txt, {len(numbers)} file(s)")
    if captions != len(numbers):
        problems.append(
            f"{captions} caption(s) in readme.txt but {len(numbers)} screenshot file(s)"
        )

    # Nothing in the package may name a style or script tag in source form.
    problems.extend(check_markup(args.out))


    # The archive layout is the plugin's install path: one top-level directory,
    # named after the slug, holding the main file.
    if os.path.exists(args.out):
        with zipfile.ZipFile(args.out) as archive:
            names = archive.namelist()
        tops = sorted({name.split("/")[0] for name in names})
        print(f"check top-level: {tops}")
        if tops != [SLUG]:
            problems.append(f"top-level directory is {tops}, expected ['{SLUG}']")
        if f"{SLUG}/phoenixseo-page-builder.php" not in names:
            problems.append(f"{SLUG}/phoenixseo-page-builder.php missing from the archive")
        stray = [n for n in names if n.split("/")[0] != SLUG]
        if stray:
            problems.append(f"{len(stray)} entries outside {SLUG}/: {stray[:3]}")

    if problems:
        print("FAIL " + "; ".join(problems), file=sys.stderr)
        return 1
    print("ERGEBNIS: Paket sauber")
    return 0


if __name__ == "__main__":
    sys.exit(main())
