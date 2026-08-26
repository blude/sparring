---
name: Datenschutz
kurzbeschreibung: Die Behandlung personenbezogener Daten nach rechtlichen Vorgaben wie der DSGVO.
typ: konzept
stand: 2026-04-26
---

# Datenschutz

**Definition:** Die Behandlung personenbezogener Daten nach rechtlichen Vorgaben wie der DSGVO. Auf der Elementebene heißt das: Welche Entitäten enthalten personenbezogene Attribute, wie lange werden sie gespeichert, an wen gehen sie weiter?

## Erläuterung

Datenschutz ist ein Querschnittsthema, das auf jeder Ebene des Ebenenmodells anders greift:

- **Lösungsebene:** Datenschutz als Qualitätsanforderung an die Wertschöpfungsarchitektur — welche Daten werden überhaupt gesammelt, und warum?
- **Systemebene:** Auslegung der Systemarchitektur — Verschlüsselung, Datensparsamkeit, Löschbarkeit als Entwurfsentscheidungen.
- **Elementebene:** Datenschutz-Eigenschaften der Entitäten — welche Attribute sind personenbezogen, wie lange bleiben sie gespeichert, wer bekommt sie?

Auf der Elementebene ist Datenschutz eine **externe Randbedingung** (vgl. Kap03#3.4.5): Die DSGVO ist nicht verhandelbar — sie setzt verbindliche Grenzen für die Gestaltung der Entitäten.

Konkrete Prüffragen auf der Elementebene:
- Welche Entitäten enthalten personenbezogene Attribute?
- Ist die Speicherung notwendig (Datensparsamkeit)?
- Wie lange werden die Daten gespeichert? Wann werden sie gelöscht?
- An wen werden sie weitergegeben?

## Abgrenzung

- **Datenschutz ≠ IT-Sicherheit.** IT-Sicherheit schützt Daten vor unbefugtem Zugriff (Verschlüsselung, Zugriffskontrollen). Datenschutz regelt, welche Daten überhaupt erhoben und verarbeitet werden dürfen. Beides ist relevant, aber konzeptionell zu trennen.
- **Datenschutz auf der Elementebene ≠ Datenschutz auf der Lösungsebene.** Auf der Lösungsebene ist Datenschutz eine strategische Qualitätsanforderung an die Wertschöpfungsarchitektur. Auf der Elementebene ist er eine konkrete Anforderung an die Entitäten.

## Beispiele

**Familie Heiner — Entität Lieferadresse:**
Die Lieferadresse enthält personenbezogene Attribute (Name, Straße, PLZ, Ort). Datenschutz heißt hier:
- Ist die Speicherung notwendig? Ja, für die Zustellung.
- An wen wird sie weitergegeben? Nur an den Fahrer, nicht an Marketing.
- Wie lange? 30 Tage nach Zustellung löschen.

**NoteMate — Entität Benutzerkonto:**
Personenbezogene Attribute: Benutzer-ID, Geräteliste. Datenschutz: End-to-End-Verschlüsselung; Notizen dürfen auch für NoteMate nicht lesbar sein.

## Verwendung im Buch

- Kap03#3.4.5 führt Datenschutz als externe Randbedingung auf der Elementebene ein.
- Datenschutz auf der Lösungsebene: Qualitätsanforderung an die Wertschöpfungsarchitektur.
- Datenschutz auf der Systemebene: Architekturentscheidungen (Verschlüsselung, Datensparsamkeit, Löschbarkeit).

## Verwandt

- Entität — der primäre Anker für Datenschutz auf der Elementebene
- Qualitätsanforderung — formale Anforderung, die aus Datenschutzvorgaben abgeleitet wird
- Wertschöpfungsarchitektur — Datenschutz als Qualitätsanforderung auf der Lösungsebene
- Systemarchitektur — Datenschutz als Architekturentscheidung auf der Systemebene
- Elementebene — die Ebene, auf der Datenschutz als Entitäts-Eigenschaft greift
