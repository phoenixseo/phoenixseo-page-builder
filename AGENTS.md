# AGENTS.md — Sdc Page Builder

## Projektwissen (WICHTIG)

Das spezifische und allgemeine Wissen für dieses Projekt liegt im **Obsidian-Vault** unter:

`vault/vault_agency/projects/sdc-page-builder`

- Lies bei Fragen zu diesem Projekt **zuerst** im Vault nach (Kataloge/Index-Dateien als Einstieg, dann gezielt die Dokumente).
- Das Projektwissen ist dort die **Quelle der Wahrheit** — nicht die Session-Historie.
- Ergänze neues Wissen im Vault, wenn du etwas Wichtiges herausfindest (Brain-First-Suchleiter beachten).

## Projekt-Kontext

Unterprojekt von PHOENIXSEO — **PhoenixSEO Page Builder**: komponentenbasierter Page-Builder für WordPress, der ACF- oder SCF-Felder als Bausteine nutzt und Seiten als ein JSON-Dokument speichert.

- **Repo:** `phoenixseo/phoenixseo-page-builder` (öffentlich, im wp.org-Verzeichnis seit 0.3.1)
- **Stand:** 0.3.3 — drei Statusspalten in der Übersicht (Definition / PHP / CSS); 383 PHP-Tests / 2209 Assertions grün auf ACF, SCF und WP 6.4.14
- **Altlasten:** `../../_archiv/projekte/pods-repeater/` — eingestellter Pods-Repeater-Weg, am 2026-11-14 gepackt (`pods-repeater_2026-11-02_frozen.tar.gz`, Repo-Ende `bfd4bf2`), Ordner gelöscht

## Hinweise

- Workspace-Ordner: `workspaces/sdc-page-builder/`
- In diesem Ordner liegt nur, was zum aktuellen Projekt gehört. Alles zum Pods-Fork liegt gepackt in `../../_archiv/projekte/pods-repeater/`.
- Plugin-Code unter `plugin/`, Teststack unter `test-scf/`.
