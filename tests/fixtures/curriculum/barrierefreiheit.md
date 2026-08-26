---
name: Barrierefreiheit
kurzbeschreibung: Qualitätseigenschaft und Randbedingung, die uneingeschränkte Zugänglichkeit für Menschen mit unterschiedlichen Fähigkeiten als Entwurfsziel verankert — Manifestation der Force Inklusion auf der Elementebene.
typ: konzept
stand: 2026-05-02
---

# Barrierefreiheit

**Definition:** Die Eigenschaft eines Elements, von Menschen mit unterschiedlichen Fähigkeiten gleichermaßen genutzt werden zu können — sensorisch (Sehen, Hören), motorisch, kognitiv und sprachlich.

## Erläuterung

Barrierefreiheit hat eine **Doppelnatur**, die im Entwurf sauber getrennt werden muss:

- **Als Qualität (3.4.4):** Barrierefreiheit ist primär eine Eigenschaft des User Interfaces — sie sagt, *wie gut* ein Element für Menschen mit unterschiedlichen Fähigkeiten erreichbar ist. Wer Barrierefreiheit nur als Pflichtprogramm behandelt, verpasst die Chance, sie als Qualitätsgewinn zu gestalten.

- **Als Randbedingung (3.4.5):** Das gesetzliche Gerüst dahinter ist eine externe Randbedingung — verbindlich, unabhängig von der eigenen Priorisierung.

Die Trennlinie: *Was* an Barrierefreiheit erreicht wird = Qualität. *Welches Mindestmaß gesetzlich gefordert ist* = Randbedingung.

**Gesetzliche Grundlagen:**
- **WCAG 2.1** — internationaler Standard (Web Content Accessibility Guidelines)
- **ADA** — Americans with Disabilities Act (USA)
- **BFSG** — Barrierefreiheitsstärkungsgesetz (Deutschland, gilt seit 2025)

Konkrete Prüffragen: Kann das Element mit einem Screen-Reader bedient werden? Sind Farbkontraste ausreichend (mindestens 4,5:1 nach WCAG AA)? Lässt sich die Navigation per Tastatur steuern? Gibt es Inhalte in einfacher Sprache?

Vertieft wird Barrierefreiheit in Kap44.

## Abgrenzung

- **Barrierefreiheits-Eigenschaft ≠ gesetzliche Barrierefreiheits-Vorgabe.** Die Eigenschaft ist eine Qualitätsfrage; die Vorgabe ist eine Randbedingung. Beide betreffen denselben Sachverhalt, werden aber im Arbeitsmodell an verschiedenen Stellen verhandelt.
- **Barrierefreiheit ≠ Usability.** Usability zielt auf Effizienz und Effektivität für einen bestimmten Benutzertyp. Barrierefreiheit zielt darauf, dass das Element überhaupt für Menschen mit unterschiedlichen Fähigkeiten zugänglich ist — das ist eine vorgelagerte Frage. Ohne ausreichende Barrierefreiheit ist Usability für einen Teil der Nutzenden gar nicht erreichbar.
- **Barrierefreiheit ≠ User Experience.** UX schließt Barrierefreiheit ein, geht aber über die reine Erreichbarkeit hinaus (emotionale Qualität, Vertrauen usw.).

## Verhältnis zu Forces

Barrierefreiheit ist keine Force im strikten Sinn — kein letzter Zweck (→ Force). Wer fragt "Warum Barrierefreiheit?", erhält die Antwort: "Weil alle Menschen gleichberechtigt teilhaben sollen" — und das verweist direkt auf Inklusion als letzten Zweck.

Barrierefreiheit ist Manifestation der Force Inklusion:
- Als **Qualitätsanforderung**: operationalisiert den Wert Inklusion auf der Elementebene (WCAG-Konformität, Screenreader-Kompatibilität, Tastaturnavigation)
- Als **Randbedingung**: BFSG (Deutschland, seit 2025), WCAG 2.1, ADA (USA) erzwingen ein Mindestmaß — unabhängig davon, ob Inklusion als Force gesetzt ist

Wer barrierefrei baut, weil er das Gesetz fürchtet, handelt aus einer Randbedingung. Wer barrierefrei baut, weil er Inklusion als Wert hält, handelt aus der Force Inklusion. Der Unterschied zeigt sich im Entwurfshandeln: Die Force zieht weiterreichende Strukturentscheidungen (Multimodalität, Benutzertypen-Architektur, Wahl barrierefreier Infrastruktur); die Randbedingung erzwingt nur das Minimum.

## Beispiele

**Familie Heiner — Admin-Dashboard und Shop-Frontend:**
Der Online-Shop fällt seit 2025 unter das BFSG — bestimmte Anforderungen sind zu erfüllen. Als Force: Wenn Familienmitglieder mit Sehschwäche das Dashboard täglich nutzen, ist WCAG-konforme Farbkontrastgestaltung und Screenreader-Kompatibilität kein "Nice-to-have". Das zieht auf Elementebene konkrete Anforderungen an die Entitäts-Attribute (Alternativtexte für Produktbilder) und die Use-Case-Struktur (vollständige Tastatur-Navigierbarkeit des Bestellvorgangs).

**NoteMate:** Für eine Notiz-App für Studierende ist Barrierefreiheit eine relevante Force — ein nicht unerheblicher Anteil der Nutzerschaft hat Lese- oder motorische Einschränkungen. Sprachbasierte Notizeingabe als Ergänzung zur Texteingabe wäre eine barrierefreie Erweiterung der UI-Modalität.

**Greengineers:** Komplexeste Barrierefreiheits-Anforderung: Der Homeserver ist ein physisches Gerät mit Einrichtungsinterface. Sind die Energie-Dashboards für farbenblinde Nutzer lesbar (Ampelfarben rot/grün sind problematisch)?
