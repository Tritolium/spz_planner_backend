# Historisierung der Anwesenheitsdaten

## Tabelle `tblAttendenceHistory`

Die Tabelle `tblAttendenceHistory` speichert jede inhaltliche Änderung der Anwesenheitsstatustabelle `tblAttendence`.

| Spalte                | Typ         | Beschreibung |
| --------------------- | ----------- | ------------ |
| `history_id`          | INT, PK     | Laufender Primärschlüssel (AUTO_INCREMENT).
| `member_id`           | INT, FK     | Referenz auf das Mitglied, dessen Status sich geändert hat.
| `event_id`            | INT, FK     | Referenz auf die betroffene Veranstaltung.
| `previous_attendence` | INT, NULL   | Vorheriger Status (`NULL` bei erstmaligem Eintrag).
| `new_attendence`      | INT         | Aktueller Status nach der Änderung.
| `changed_at`          | DATETIME    | Zeitstempel der Änderung (Standard: `CURRENT_TIMESTAMP`).
| `changed_by`          | INT, NULL   | Optionaler Verweis auf das Mitglied, das die Änderung ausgelöst hat.

Die Foreign-Keys stellen sicher, dass Historieneinträge nur für vorhandene Mitglieder und Veranstaltungen existieren. Historische Einträge werden beim Löschen der zugehörigen Mitglieder oder Events automatisch entfernt (`ON DELETE CASCADE`). Der optionale Verweis `changed_by` wird bei nicht mehr existierenden auslösenden Mitgliedern automatisch auf `NULL` gesetzt.

## Automatisierte Befüllung

In der ursprünglichen Planung war vorgesehen, ergänzend zu den PHP-Schreibpfaden zwei Datenbank-Trigger für `tblAttendence` einzurichten. Auf dem produktiven SQL-Server sind Trigger jedoch nicht erlaubt. Daher erfolgt die Historisierung ausschließlich über die Anwendungslogik:

- Jede Aktualisierung der Attendance-Endpunkte ruft `updateSingleAttendence(..., $changed_by)` auf und legt unmittelbar danach einen Datensatz in `tblAttendenceHistory` an.
- Auch Systempfade (z. B. automatische Einträge beim Anlegen neuer Events) nutzen dieselbe Funktion und erzeugen dadurch Historieneinträge.

Sollten künftig weitere Schreibpfade hinzukommen, müssen diese ebenfalls `updateSingleAttendence` verwenden oder die Historisierung analog implementieren.

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
- Doppelte Einträge werden verhindert, indem vor dem Einfügen geprüft wird, ob sich der Statuswert tatsächlich geändert hat.
- Historische Daten lassen sich sicher archivieren, da sie keine wechselseitigen Abhängigkeiten außer den Foreign-Keys besitzen.
