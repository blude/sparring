---
name: Ebenenmodell digitaler Lösungen
kurzbeschreibung: Das spezifische Modell der drei Ebenen — Lösung, System, Element —, mit dem digitale Lösungen in handhabbare Granularitäten zerlegt werden.
typ: konzept
stand: 2026-04-19
---

# Ebenenmodell digitaler Lösungen

**Definition:** Das Ebenenmodell zerlegt eine digitale Lösung in drei konzeptionelle Skalierungsstufen — [[loesungsebene]], [[systemebene]] und [[elementebene]] —, die sich in ihrer Granularität unterscheiden, aber auf jeder Stufe dieselben Entwurfsaspekte (Ziele, Form, Funktion, Qualität, Randbedingungen) bearbeiten.

## Erläuterung

Digitale Lösungen sind zu komplex, um sie auf einer einzigen Ebene zu entwerfen. Das Ebenenmodell ist eine konkrete Anwendung des allgemeineren Prinzips der [[abstraktionsebenen]]: Es wählt genau drei Stufen, benennt sie und gibt jeder eine eigene Sprache und Logik.

Die Analogie zur Baukunst liegt nahe: Erst Gebäudeform und Geschosse, dann Grundriss und Fassade, dann einzelne Räume und Installationen. Die Skalen hängen voneinander ab, aber auf jeder Skala wird fokussiert gearbeitet.

Jede Ebene hat ihre eigene **Granularität, Sprache und Logik**. Auf der Lösungsebene spricht man die Sprache des Geschäfts (Wertversprechen, Geschäftsprozesse, Ertragsmodell). Auf der Systemebene die Sprache der technischen Struktur (Architektur, Schnittstellen, Szenarien). Auf der Elementebene die Sprache der konkreten Bausteine (UI, Entitäten, Use Cases).

Die Ebenen wirken in **drei Richtungen** zusammen:

- **Top-down erzwingt:** Obere Ebenen geben den Rahmen vor. Die Vision auf der Lösungsebene zwingt die Systemebene zu bestimmten Entscheidungen.
- **Bottom-up schränkt ein:** Untere Ebenen melden Grenzen zurück. Wenn eine technische Zielvorgabe auf der Elementebene nicht erreichbar ist, muss die Systemebene reagieren.
- **Bottom-up ermöglicht:** Das Wissen über technologische Möglichkeiten auf den unteren Ebenen eröffnet neue strategische Räume auf der Lösungsebene — siehe [[materialkompetenz]].

## Abgrenzung

- Die Ebenen sind **konzeptuell**, nicht implementierungstechnisch. Sie existieren im Denken und in der Struktur des Entwurfs, nicht in der konkreten Code- oder Systemlandschaft.
- Die Ebenen sind **nicht hierarchisch im Sinne von Wichtigkeit** — keine ist "wichtiger" als eine andere. Sie unterscheiden sich im Fokus.
- Die Ebenen sind **nicht sequenziell abzuarbeiten**. Ein guter Entwurf verhandelt zwischen den Ebenen iterativ.
- Die Grenze zwischen Entwurf und Realisierung (konkrete Produktauswahl, z. B. "PostgreSQL") gehört **nicht** zum Ebenenmodell. Das Modell beschreibt Entwurf, nicht Umsetzung.
- Das Ebenenmodell ist **nicht** mit [[abstraktionsebenen]] gleichzusetzen. Abstraktionsebenen sind ein allgemeines Prinzip; das Ebenenmodell ist eine konkrete, für digitale Lösungen gewählte Ausprägung mit genau drei Stufen.

## Beispiele

**NoteMate** (einfach):

- Lösungsebene: Notiz-App, Abo-Modell, drei Studierende als Team.
- Systemebene: Frontend-App, Backend-Server, Cloud-Speicher, Payment-Provider.
- Elementebene: Das Backend mit Entitäten "Notiz" und "Nutzer", Use Case "Notiz synchronisieren", technische Schnittstellen zur App.

**Familie Heiner** (hybrid):

- Lösungsebene: Online-Shop für Bio-Produkte mit Lieferdienst.
- Systemebene: Shop-Frontend, Bestellungs-Backend, bestehendes Lager-System, Fahrer-App.
- Elementebene: Das Bestellungs-Backend mit Admin-Dashboard, Entitäten "Bestellung" und "Lieferadresse", Use Case "Bestellung aufgeben".

**Greengineers** (ökosystemisch):

- Lösungsebene: Drei-Partner-Ökosystem zur Lastverschiebung im Stromnetz.
- Systemebene: Zentrale Entscheidungs-Engine, lokale Homeserver, externe Datenquellen.
- Elementebene: Die Entscheidungs-Engine als Software-Element; der Homeserver als Hardware-Element mit physischem Aufbau.

## Verwendung im Buch

- [[Kap03]] führt das Ebenenmodell ein (Abschnitt 3.1 Überblick, 3.2 Lösungsebene, 3.3 Systemebene, 3.4 Elementebene, 3.5 Zusammenspiel).
- [[Kap06]] nimmt die Ebenen als Strukturierungshilfe wieder auf und zeigt ihre rekursive Anwendbarkeit; dort wird auch das allgemeine Prinzip der [[abstraktionsebenen]] eingeführt.
- [[Teil_II]] ist entlang der Ebenen strukturiert (Muster-Kapitel 10–18 Lösungsebene, 19–21 Systemebene, 22–27 Elementebene).

## Verwandt

- [[abstraktionsebenen]] — das allgemeine Prinzip, von dem das Ebenenmodell eine spezifische Ausprägung ist
- [[loesungsebene]] · [[systemebene]] · [[elementebene]]
- [[ffq-modell]] — die auf jeder Ebene wiederkehrende Struktur Form/Funktion/Qualität
- [[arbeitsmodell]] — Ziele, Randbedingungen und FFQ zusammen
- [[materialkompetenz]] — die Voraussetzung für "Bottom-up ermöglicht"
- [[konsistenzregeln]] — das Prüfinstrument zwischen den Ebenen
