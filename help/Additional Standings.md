<!--
# tournaments module
# help: additional standings
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

# Additional Standings

Besides the main standings table, a tournament can show **additional
standings** filtered by sex or age group.

Configure them on the tournament record in the field **Standings
tables**: enter a comma-separated list of **lowercase** abbreviations
(without spaces).

Example: `w,m,alt,jung`

## Available abbreviations

- `w` — female participants
- `m` — male participants
- `alt` — oldest age group permitted in the tournament (by year of birth)
- `jung` — all younger age groups

## Planned abbreviations

These filters are not implemented yet:

- `u8` etc. — under X years old
- `60+` — over 60 years old
- `dwz-u2000` — DWZ under 2000
- `elo-u2000` — Elo under 2000
- `dwz-2000+` — DWZ over 2000, and similar patterns

## Links on tournament pages

Filtered standings are always available at URLs such as
`/tournament-identifier/tabelle/w/` (replace `w` with the abbreviation).

On public tournament pages, links to these tables are shown **only** for
abbreviations listed in **Standings tables** on the tournament record.
