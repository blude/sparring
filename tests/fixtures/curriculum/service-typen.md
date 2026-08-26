---
name: Servicetypen
kurzbeschreibung: Topologie-Konkretisierung der Grundgestalt Produkt ↔ Service — beschreibt die vier Serviceformen (analog, digital für Menschen, digital für Maschinen, hybrid) als Typ-Palette innerhalb der Service-Seite.
typ: muster
klasse: topologiemuster
ebene: loesung
stand: 2026-05-02
---

# Servicetypen

> **Hinweis zur Klassifikation (2026-05-02):** Servicetypen werden als *Topologiemuster* (Typ-Topologie) eingeordnet — eine Palette von Serviceformen, die innerhalb der Grundgestalt Produkt ↔ Service auf der Service-Seite auftreten können. Vormals als Grundgestalt geführt. Die inhaltliche Ausarbeitung bleibt vollständig gültig.

**Definition:** Servicetypen beschreiben das Spektrum der strukturellen Grundformen, die ein Service in einer Wertschöpfungsarchitektur annehmen kann — analoger Service, digitaler Service für Menschen, digitaler Service für Maschinen oder hybrider Service — jede mit eigenem Form-Funktion-Bündel und spezifischen Qualitätsanforderungen.

## Klassifikation

- **Klasse:** Grundgestalt
- **Ebene:** Lösungsebene
- **Aspekt-Bündelung:** Form: Wertschöpfungsarchitektur → Ertragsmodell, Partner (Wertschöpfungsquelle und Betriebscharakter); Funktion: Geschäftsprozess (Matching, Betrieb, Orchestrierung — je nach Typ grundlegend verschieden)

## Voraussetzung

Diese Grundgestalt setzt die übergeordnete Entscheidung Produkt ↔ Service voraus: Erst wenn die Lösung auf der Service-Seite des Spektrums positioniert ist, wird die Wahl des Service-Typs relevant.

## Spektrum: Die vier Servicetypen

### Typ 1 — Analoger Service

**Charakter:** Die Kernleistung ist menschlich; die digitale Lösung ist Enabler — sie orchestriert, vermittelt und koordiniert, aber die eigentliche Wertschöpfung sitzt im Menschen. Matching-Plattformen, Handwerker-Koordinations-Systeme, Freelancer-Marktplätze.

**Konsequenzen:** Dreiseitiges Ökosystem (Kunde, Leistungserbringer, Plattform), Matching-Logik als komplexer Algorithmus mit Businessregeln, Koordination in Echtzeit (Live-Status, Benachrichtigungen). Auf der Systemebene: Multiple Benutzertypen mit unterschiedlichen Use Cases; auf der Elementebene: Kalender-Integration, Karten-APIs, Bewertungs-Entitäten.

**Prägende Antwort:** Zwei-seitiger Marktplatz mit algorithmischem Matching und Echtzeit-Koordination — erzwingt eine Benutzertypen-Architektur mit klar getrennten Rollen, Matching-Algorithmus als eigenständiges Element, Event-basierte Benachrichtigungsarchitektur.

**Offene Antwort:** Einfaches Verzeichnis mit manuellem Kontaktaufbau (Telefonbuch für Handwerker) — keine Matching-Logik, keine Echtzeit-Koordination erzwungen.

### Typ 2 — Digitaler Service für Menschen

**Charakter:** Der Service ist vollständig digital, richtet sich an menschliche Nutzer. SaaS-Anwendungen, Cloud-Speicher, Streaming, Video-Konferenz. Verfügbarkeit und Zuverlässigkeit sind das primäre Wertversprechen.

**Konsequenzen:** Hochverfügbarkeit als Designzwang (24/7, kein Wartungsfenster), Mandantenfähigkeit (mehrere Kunden teilen Infrastruktur, sehen einander nicht), elastische Skalierung (100 bis 100.000 Nutzer ohne Architekturwechsel). Auf der Systemebene: Server-dominierte Architektur; auf der Elementebene: Web- oder App-Frontend, starkes Backend (APIs, Datenbanken, Caches, Message Queues).

**Prägende Antwort (Freemium):** Freemium als besonders prägende Variante erzwingt Tier-Trennung auf der Systemebene (Multi-Tenant mit Tier-Segmentierung), differenzierte UI-Zugänge auf der Elementebene, Access-Control-Use-Cases und Pay-Wall-Entitäten.

**Offene Antwort:** Single-Tenant-SaaS ohne Freemium — Hochverfügbarkeit bleibt Pflicht, aber Tier-Architektur entfällt.

### Typ 3 — Digitaler Service für Maschinen

**Charakter:** Der Konsument ist kein Mensch, sondern ein System. APIs, IoT-Backends, Machine-Learning-Services, Message-Broker. Usability als Force entfällt; stattdessen dominieren Latenz, Durchsatz und Zuverlässigkeit als Qualitätsanforderungen.

**Konsequenzen:** Das API ist das zentrale Element (statt einem User Interface), Rate Limiting und Fehlerbehandlung als Pflichtbestandteile, Versionierbarkeit von Schnittstellen als Stabilitätsversprechen gegenüber Integratoren. Auf der Systemebene: API-Gateway, Message-Broker, Backend-Services ohne UI; auf der Elementebene: REST-, GraphQL- oder gRPC-Interfaces, strukturierte Datenformate, Webhooks.

**Prägende Antwort:** Öffentliche API-Plattform mit Entwickler-Ökosystem — erzwingt Entwickler-Portal, Sandbox-Umgebung, API-Versionierungsstrategie, SLA-Dokumentation als eigenständige Elemente.

**Offene Antwort:** Interne API (nur hausintern genutzt) — kein Developer-Onboarding, kein öffentliches Versprechen auf Stabilität erzwungen.

### Typ 4 — Hybrider Service

**Charakter:** Physische und digitale Leistungen sind orchestriert — digitale Initiation, physische Ausführung, digitale Verfolgung. Lieferdienste, Telemedizin, Reparatur-Plattformen. Die Übergänge zwischen analog und digital sind das eigentliche Designproblem.

**Konsequenzen:** Tracking-Systeme und Dispatch-Logik als eigenständige Systemkomponenten, mobile Apps für Feldkräfte mit Offline-Funktionalität, Echtzeit-Benachrichtigungen für Nutzer. Auf der Systemebene: Zwei Benutzertypen-Welten (Nutzer und Feldkraft) mit sehr unterschiedlichen Use-Case-Profilen; auf der Elementebene: GPS-Tracking, Push-Notifications, Offline-Sync.

**Prägende Antwort:** Live-GPS-Tracking des Feldmitarbeiters mit Echtzeit-Statusupdates (Fahrer ist 5 Minuten entfernt) — erzwingt GPS-Integration, Event-basierte Statusarchitektur, Offline-fähige mobile App.

**Offene Antwort:** Statische Statusmeldungen ("Ihr Paket ist unterwegs") ohne Echtzeit-Tracking — keine GPS-Infrastruktur erzwungen.

## Treibende Forces

- Profitorientierung: Treibt beim digitalen Service für Menschen in Richtung Abo-Modell (wiederkehrende Erträge, langer Customer-Lifetime-Value). Beim analogen Service: Plattform-Provision als indirektes Ertragsmodell. Beim digitalen Service für Maschinen: API-Zugangsgebühren, nutzungsbasierte Abrechnung.
- Usability: Zieht beim digitalen Service für Menschen Service-Varianten mit reibungslosem Onboarding — direkter Usability-Vorteil am Einstiegspunkt (kein Installationsaufwand). Beim hybriden Service: nahtlose Übergänge zwischen digitaler und physischer Phase als usability-kritischer Punkt.
- Ökologische Nachhaltigkeit: Zieht beim digitalen Service für Menschen zentralisierte, energieeffizientere Infrastruktur gegenüber dezentralisierten Produkten. Beim hybriden Service: optimierte Routenplanung (weniger Fahrtwege) als ökologischer Nebeneffekt guter Dispatch-Logik.
- Ökonomische Nachhaltigkeit: Zieht beim digitalen Service für Menschen Abo-Modelle gegenüber Einmalkäufen, weil wiederkehrende Erträge das Fortbestehen besser absichern. Beim analogen Service: Plattform-Governance als Vertrauensgrundlage für langfristige Marktteilnahme.
- Barrierefreiheit: Beim digitalen Service für Menschen: Service-Updates können Barrierefreiheits-Fehler zentral beheben, ohne auf Nutzer-Updates zu warten — struktureller Vorteil gegenüber Produkten.
- Soziale Nachhaltigkeit: Beim analogen Service: faire Vergütung der Leistungserbringer als soziale Nachhaltigkeitsfrage der Plattform-Governance. Beim hybriden Service: Arbeitsbedingungen der Feldkräfte als sozialer Gestaltungsraum.

## Konsequenzen / Propagation nach unten

**Systemebene:**
- Analoger Service erzwingt Matching-Algorithmus und Echtzeit-Koordinationssystem als eigene Elemente.
- Digitaler Service für Menschen erzwingt Hochverfügbarkeit, Mandantenfähigkeit, Skalierungsarchitektur.
- Digitaler Service für Maschinen erzwingt API-Gateway, Rate-Limiting-Mechanismus, API-Versionierungsstrategie.
- Hybrider Service erzwingt Tracking-System, Dispatch-Logik, Offline-fähige Mobile-App.

**Elementebene:**
- Analoger Service: Use Cases für zwei Benutzertypen (Kunde und Leistungserbringer), Bewertungs- und Abrechnungs-Entitäten.
- Digitaler Service für Menschen (Freemium): Tier-Entitäten, Access-Control-Use-Cases, Pay-Wall-Elemente.
- Digitaler Service für Maschinen: API-Vertragsdokumentation, SLA-Definitionen, Fehlercode-Kataloge als Elementebene-Artefakte.
- Hybrider Service: GPS-Entitäten, Auftrags-Entitäten mit Status-Lifecycle, zwei getrennte UI-Welten.

## Verwandte Strukturmuster

- Produkt ↔ Service: Die übergeordnete Grundgestalt — Servicetypen konkretisieren die Service-Seite dieses Spektrums.
- Produkt-Service-Hybrid: Wenn ein Service einen Produktanteil bekommt, entsteht eine eigene Grundgestalt jenseits der reinen Servicetypen.
- Digitales Ökosystem: Wenn mehrere Service-Typen in einer mehrseitigen Plattform zusammenkommen, entsteht ein Ökosystem — eigene Grundgestalt mit Netzwerkeffekten.
- Build vs. Rely (Entwicklung): Beim digitalen Service für Menschen und digitalen Service für Maschinen ist die Entscheidung zwischen eigenem Betrieb und Cloud-Infrastruktur unmittelbar verknüpft.
- UI-Modalität: Beim analogen Service und hybriden Service stellt sich die Modalitätsfrage für die Feldkraft-App und das Koordinations-Interface.

## Beispiele

**Familie Heiner** — Hybrider Service: Der Lieferdienst beginnt digital (Online-Bestellung), wird physisch (Fahrer packt und fährt) und endet mit digitalem Feedback. Der Fahrer sieht auf einer Liste, was er zu liefern hat; der Kunde verfolgt die Lieferung auf der Karte. Die Übergänge sind das Designproblem.

**Familie Heiner** — Digitaler Service für Menschen: Der Online-Shop selbst ist ein digitaler Service — er muss 24/7 verfügbar sein, darf am Samstagnachmittag (Hochlast) nicht crashen, Updates müssen ohne Betriebsunterbrechung ausgerollt werden.

**Greengineers** — Digitaler Service für Maschinen: Die Energieaustausch-Plattform bietet APIs für andere Systeme (Hausautomation, Energieunternehmen). Stabilität, Latenz und Rate Limiting sind die zentralen Qualitätsanforderungen — kein Nutzer klickt hier, ein System fragt ab.

**NoteMate** — Digitaler Service für Menschen (Ausbaustufe): Wenn NoteMate zu einem SaaS-Tool wird, muss es Hochverfügbarkeit zusagen. 99,9% Verfügbarkeit bedeutet maximal 45 Minuten Ausfallzeit pro Monat — eine echte Betriebsverpflichtung.
