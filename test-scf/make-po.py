#!/usr/bin/env python3
"""Generate plugin/languages/sdc-page-builder-de_DE.po from the POT.

The source language of the plugin is English. German lives here as a real
translation: change a string in the code, rerun `make-pot.sh`, rerun this
script, then `msgfmt`. Strings without a German entry stay untranslated on
purpose (names, URLs).
"""
import re
import sys

POT = "plugin/languages/sdc-page-builder.pot"
OUT = "plugin/languages/sdc-page-builder-de_DE.po"

# English -> German. Only the strings that came from the German source and
# the ones the i18n conversion introduced by hand.
DE = {
    # --- header / plugin meta ---
    "Component-based page builder: ACF or SCF fields as building blocks, one block per component type, rendered server-side in the editor.":
        "Komponentenbasierter Page Builder: ACF- oder SCF-Felder als Bausteine, ein Block pro Komponententyp, im Editor serverseitig gerendert.",

    # --- field types (Sdcpb_Field_Editor::definitions()) ---
    "Text Line": "Einzeiliger Text",
    "Text": "Text",
    "Placeholder": "Platzhalter",
    "Default Value": "Standardwert",
    "Maximum Characters": "Maximale Zeichenzahl",
    "Before": "Davor",
    "After": "Danach",
    "Multiline Text": "Mehrzeiliger Text",
    "Rows in the Input Field": "Zeilen im Eingabefeld",
    "Formatted Text": "Formatierter Text",
    "Number": "Zahl",
    "Smallest Value": "Kleinster Wert",
    "Largest Value": "Größter Wert",
    "Step": "Schrittweite",
    "Slider": "Schieberegler",
    "Email Address": "E-Mail-Adresse",
    "Link URL": "Link-Adresse",
    "Choice": "Auswahl",
    "Choices": "Auswahlmöglichkeiten",
    "Allow Multiple Selections": "Mehrfachauswahl erlauben",
    "Return Value": "Rückgabewert",
    "Checkboxes": "Kontrollkästchen",
    "Default Value (values, comma-separated)": "Standardwert (Werte, durch Komma getrennt)",
    "Radio Buttons": "Optionsfelder",
    "Button Group": "Schaltflächengruppe",
    "Yes/No": "Ja/Nein",
    "Show as Toggle": "Als Schalter anzeigen",
    "Image": "Bild",
    "Media": "Medium",
    "File": "Datei",
    "Link": "Link",
    "Repeater": "Wiederholer",
    "Structure": "Aufbau",
    "Label of the Add Button": "Beschriftung der Hinzufügen-Schaltfläche",
    "Editor Display": "Darstellung im Editor",
    "Blocks": "Blöcke",
    "Minimum Rows": "Mindestzahl Zeilen",
    "Maximum Rows": "Höchstzahl Zeilen",
    "Group": "Gruppe",

    # --- view helper documentation ---
    "Loop over the rows of a repeater. Helper calls inside it take $row as the last argument: sdcpb_text( 'quote', $row ), sdcpb_image( 'image', 'large', array(), $row ).":
        "Über die Zeilen eines Wiederholers laufen. Hilfsaufrufe darin nehmen $row als letztes Argument: sdcpb_text( 'quote', $row ), sdcpb_image( 'image', 'large', array(), $row ).",
}

# Plural pairs: (english singular, english plural) -> (german singular, german plural)
DE_PLURAL = {
    ("%1$d page contains a component without a view. It renders nothing until the component is back — check the preview with %2$s.",
     "%1$d pages contain a component without a view. They render nothing until the component is back — check the preview with %2$s."):
        ("%1$d Seite enthält eine Komponente ohne Ansicht. Sie rendert nichts, bis die Komponente wieder da ist — prüfe die Vorschau mit %2$s.",
         "%1$d Seiten enthalten eine Komponente ohne Ansicht. Sie rendern nichts, bis die Komponente wieder da ist — prüfe die Vorschau mit %2$s."),
    ("%1$d page, %2$d instance", "%1$d pages, %2$d instances"):
        ("%1$d Seite, %2$d Instanz", "%1$d Seiten, %2$d Instanzen"),
    ("%d season", "%d seasons"): ("%d Staffel", "%d Staffeln"),
    ("%d episode", "%d episodes"): ("%d Folge", "%d Folgen"),
    ("%d track", "%d tracks"): ("%d Track", "%d Tracks"),
}

# Deliberately untranslated: names and URLs.
KEEP_AS_IS = {
    "SDC Page Builder",
    "https://github.com/phoenixseo/sdc-page-builder",
    "PHOENIXSEO – Frank Pfabigan",
    "https://phoenixseo.de",
}

HEADER = '''msgid ""
msgstr ""
"Project-Id-Version: SDC Page Builder\\n"
"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/sdc-page-builder\\n"
"Last-Translator: PHOENIXSEO <plugins@phoenixseo.de>\\n"
"Language-Team: Deutsch\\n"
"Language: de_DE\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Domain: sdc-page-builder\\n"
'''


def quote(text):
    """PO quoting: escape what the format requires, keep everything else."""
    return '"' + text.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n") + '"'


def unquote(text):
    return text.replace('\\"', '"').replace("\\n", "\n").replace("\\\\", "\\")


def parse_pot(path):
    """Yield (context, msgid, msgid_plural) in file order."""
    src = open(path, encoding="utf-8").read()
    for block in src.split("\n\n"):
        if 'msgid "' not in block:
            continue
        def grab(key):
            m = re.search(rf'^{key} "(.*)"$', block, re.M)
            return m.group(1) if m else None
        mid = grab("msgid")
        if mid is None or mid == "":
            continue
        yield grab("msgctxt"), unquote(mid), grab("msgid_plural")


def main():
    # Invert the DE->EN table used by the conversion, so the old German
    # wording is reused rather than retyped.
    en2de = {}
    try:
        for line in open("/tmp/sdc-map.tsv", encoding="utf-8"):
            if "\t" not in line:
                continue
            de, en = line.rstrip("\n").split("\t", 1)
            if en and en not in en2de:
                en2de[en] = de
    except FileNotFoundError:
        print("warning: /tmp/sdc-map.tsv missing, only the hand-written table is used")

    parts = [HEADER]
    translated = untranslated = 0
    missing = []

    for context, msgid, plural in parse_pot("plugin/languages/sdc-page-builder.pot"):
        head = ""
        if context:
            head += "msgctxt %s\n" % quote(context)
        head += "msgid %s\n" % quote(msgid)

        if plural:
            pair = (msgid, unquote(plural))
            de = DE_PLURAL.get(pair)
            if de:
                body = "msgid_plural %s\nmsgstr[0] %s\nmsgstr[1] %s" % (
                    quote(plural), quote(de[0]), quote(de[1]))
                translated += 1
            else:
                body = "msgid_plural %s\nmsgstr[0] \"\"\nmsgstr[1] \"\"" % quote(plural)
                missing.append(pair)
                untranslated += 1
        else:
            de = DE.get(msgid) or en2de.get(msgid)
            if de:
                body = "msgstr %s" % quote(de)
                translated += 1
            else:
                body = 'msgstr ""'
                if msgid not in KEEP_AS_IS:
                    missing.append(msgid)
                untranslated += 1

        parts.append(head + body)

    open(OUT, "w", encoding="utf-8").write("\n\n".join(parts) + "\n")
    print(f"{OUT}: {translated} übersetzt, {untranslated} leer")
    for m in missing:
        print("  offen:", (m[0] if isinstance(m, tuple) else m)[:110])
    return 0


if __name__ == "__main__":
    sys.exit(main())
