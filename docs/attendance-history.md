# Historisierung der Anwesenheitsdaten

## Tabelle `tblAttendenceHistory`

Die Tabelle `tblAttendenceHistory` speichert jede inhaltliche Änderung der Anwesenheitsstatustabelle `tblAttendence`.

| Spalte                | Typ         | Beschreibung |
| --------------------- | ----------- | ------------ |
| `history_id`          | INT, PK     | Laufender Primärschlüssel.
| `member_id`           | INT, FK     | Referenz auf das Mitglied, dessen Status sich geändert hat.
| `event_id`            | INT, FK     | Referenz auf die betroffene Veranstaltung.
| `previous_attendence` | INT, NULL   | Vorheriger Status (`NULL` bei erstmaligem Eintrag).
| `new_attendence`      | INT         | Aktueller Status nach der Änderung.
| `changed_at`          | DATETIME    | Zeitstempel der Änderung (Standard: `CURRENT_TIMESTAMP`).
| `changed_by`          | INT, NULL   | Optionaler Verweis auf das Mitglied, das die Änderung ausgelöst hat.

Die Foreign-Keys stellen sicher, dass Historieneinträge nur für vorhandene Mitglieder und Veranstaltungen existieren. Historische Einträge werden beim Löschen der zugehörigen Mitglieder oder Events automatisch entfernt (`ON DELETE CASCADE`). Der optionale Verweis `changed_by` wird bei nicht mehr existierenden auslösenden Mitgliedern automatisch auf `NULL` gesetzt.

## Automatisierte Befüllung über Trigger

Zwei Datenbank-Trigger sorgen dafür, dass jede Einfügung oder Aktualisierung von Datensätzen in `tblAttendence` auch dann protokolliert wird, wenn die Änderung nicht über den PHP-Code erfolgt:

- `trg_tblAttendence_after_insert`: legt nach einem `INSERT` automatisch einen Historieneintrag mit `previous_attendence = NULL` an.
- `trg_tblAttendence_after_update`: erzeugt nach einem `UPDATE` einen Historieneintrag, sobald sich der Statuswert (`attendence`) geändert hat.

Die Anwendung schreibt zusätzlich bei allen bestehenden Update- und Insert-Pfaden explizit einen Historieneintrag und kann – soweit verfügbar – den auslösenden Nutzer (`changed_by`) hinterlegen.

## Auswertung und Reporting

### Schnelle Sicht auf den Änderungsverlauf

```sql
CREATE OR REPLACE VIEW vw_attendence_change_log AS
SELECT
    h.history_id,
    h.changed_at,
    h.member_id,
    m.forename,
    m.surname,
    h.event_id,
    e.title,
    h.previous_attendence,
    h.new_attendence,
    h.changed_by,
    cb.forename AS changed_by_forename,
    cb.surname  AS changed_by_surname
FROM tblAttendenceHistory h
LEFT JOIN tblMembers m ON h.member_id = m.member_id
LEFT JOIN tblEvents  e ON h.event_id = e.event_id
LEFT JOIN tblMembers cb ON h.changed_by = cb.member_id;
```

Diese View verknüpft alle relevanten Stammdaten und kann direkt von Reporting-Tools abgefragt werden.

### Analyse der Status-Entwicklungen

- **Conversion-Analyse**: Anteil der Mitglieder, die von `-1` (keine Rückmeldung) zu `1` (Zusagen) wechseln, je Veranstaltung.

  ```sql
  SELECT
      event_id,
      COUNT(*) AS transitions,
      SUM(previous_attendence = -1 AND new_attendence = 1) AS conversions
  FROM tblAttendenceHistory
  WHERE changed_at BETWEEN :from AND :to
  GROUP BY event_id;
  ```

- **Bearbeiteraktivität**: Wer nimmt wie viele Änderungen vor?

  ```sql
  SELECT
      h.changed_by,
      cb.forename,
      cb.surname,
      COUNT(*) AS changes_total
  FROM tblAttendenceHistory h
  LEFT JOIN tblMembers cb ON h.changed_by = cb.member_id
  WHERE h.changed_by IS NOT NULL
  GROUP BY h.changed_by, cb.forename, cb.surname
  ORDER BY changes_total DESC;
  ```

- **Verlauf einzelner Mitglieder**: Änderungszeitstrahl für individuelle Coaching-Gespräche.

  ```sql
  SELECT
      h.changed_at,
      h.previous_attendence,
      h.new_attendence,
      e.date,
      e.title
  FROM tblAttendenceHistory h
  JOIN tblEvents e ON h.event_id = e.event_id
  WHERE h.member_id = :member_id
  ORDER BY h.changed_at;
  ```

### Integration in BI-Tools

Durch die klaren Foreign-Keys kann `tblAttendenceHistory` problemlos in bestehende BI- oder Dashboard-Lösungen aufgenommen werden. Die oben genannte View `vw_attendence_change_log` dient als zentraler Einstiegspunkt und kann beispielsweise nach `event_id`, `changed_by` oder Zeitfenstern gefiltert werden. Für zeitbasierte Auswertungen empfiehlt sich ein zusätzlicher Index auf `changed_at` (z. B. `CREATE INDEX idx_attendence_history_changed_at ON tblAttendenceHistory(changed_at);`), falls große Datenmengen erwartet werden.

## Hinweise zur Datenqualität

- Mehrfachänderungen innerhalb weniger Sekunden (z. B. Korrekturen) werden chronologisch festgehalten und können bei Bedarf zusammengefasst werden.
- Trigger und Anwendung erzeugen identische Historieneinträge; doppelte Einträge werden durch die Konsistenzprüfung (Vergleich von `previous_attendence` und `new_attendence`) vermieden.
- Historische Daten lassen sich sicher archivieren, da sie keine wechselseitigen Abhängigkeiten außer den Foreign-Keys besitzen.
