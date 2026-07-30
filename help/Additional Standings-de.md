<!--
# tournaments module
# help: additional standings, German
#
# Part of »Zugzwang Project«
# https://www.zugzwang.org/modules/tournaments
#
# @author Gustaf Mossakowski <gustaf@koenige.org>
# @copyright Copyright © 2026 Gustaf Mossakowski
# @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
#
# Variables
# audience = editor
-->

# Zusätzliche Tabellenstände

Neben dem Haupttabellenstand kann ein Turnier **zusätzliche Tabellenstände**
anzeigen — gefiltert nach Geschlecht oder Altersgruppe.

Konfiguration im Turnierdatensatz im Feld **Tabellenstände**: als
kommagetrennte Liste von **Kleinbuchstaben**-Abkürzungen (ohne Leerzeichen).

Beispiel: `w,m,alt,jung`

## Verfügbare Abkürzungen

- `w` — weibliche Teilnehmerinnen
- `m` — männliche Teilnehmer
- `alt` — ältester für das Turnier zugelassener Jahrgang
- `jung` — alle jüngeren Jahrgänge

## Geplante Abkürzungen

Diese Filter sind noch nicht implementiert:

- `u8` usw. — unter X Jahren
- `60+` — über 60-Jährige
- `dwz-u2000` — DWZ unter 2000
- `elo-u2000` — Elo unter 2000
- `dwz-2000+` — DWZ über 2000 usw.

## Links auf Turnierseiten

Gefilterte Tabellenstände sind immer unter URLs wie
`/turnier-kennung/tabelle/w/` erreichbar (`w` durch die Abkürzung
ersetzen).

Auf öffentlichen Turnierseiten werden Links zu diesen Tabellen **nur**
für im Feld **Tabellenstände** eingetragene Abkürzungen angezeigt.
