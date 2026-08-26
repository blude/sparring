---
name: Parallelität
kurzbeschreibung: Aspektmuster der Lösungsebene, das pro Prozess entscheidet, wie viele Instanzen gleichzeitig existieren dürfen — und damit Skalierungsarchitektur, Concurrency-Mechanismen und Ressourcenplanung prägt.
typ: muster
klasse: aspektmuster
ebene: loesung
stand: 2026-05-02
---

# Parallelität

> **Hinweis zur Klassifikation (2026-05-02):** Parallelität wurde von *Grundgestalt* zu *Aspektmuster* umklassifiziert. Grund: Die Frage "Wie viele Instanzen?" wird *pro Prozess* beantwortet — bauteilweise Variation (Aspektmuster). Die inhaltliche Ausarbeitung bleibt vollständig gültig.

**Definition:** Parallelität ist ein Aspektmuster der Lösungsebene, das pro Prozess entscheidet, wie viele Instanzen gleichzeitig existieren dürfen — und damit die Skalierungsarchitektur, Concurrency-Mechanismen und Ressourcenplanung auf Systemebene prägt.

## Klassifikation

- **Klasse:** Aspektmuster (vormals: Grundgestalt)
- **Ebene:** Lösungsebene
- **Aspekt-Bündelung:** Form: Systemarchitektur (Concurrency-Infrastruktur, Load-Balancer, Ressourcenpools); Funktion: Geschäftsprozess (Instanziierungscharakter — seriell vs. kapazitätsbegrenzt vs. elastisch)

## Spektrum

Das Spektrum läuft von streng seriell über kapazitätsbegrenzt bis unbegrenzt elastisch.

**Einmaligkeitsprozess** (offene Antwort): Nur eine Instanz darf gleichzeitig existieren. Serialisierung ist Designregel. Einfachste Architektur — kein Konfliktverwaltungsaufwand. Aber: Die Skalierung geht gegen null. Akzeptabel für spezialisierte, ressourcengebundene Szenarien (ein Techniker, eine Werkstatt). Propagiert wenig auf Systemebene: Locking-Mechanismus reicht.

**Definiert-gleichzeitiger Prozess** (moderat prägende Antwort): Genau N Instanzen gleichzeitig. Bekannte Obergrenze ermöglicht Kapazitätsplanung. Wenn die Grenze erreicht ist, muss das System Warteschlangen oder Ablehnungen implementieren. Erzwingt Resource-Pooling oder License-Management-Elemente. Die Grenze ist oft wirtschaftlich begründet (Lizenzkosten, Serverkapazität).

**Offen-gleichzeitiger Prozess** (stark prägende Antwort): Beliebig viele Instanzen gleichzeitig. Elastische Skalierung ohne Obergrenze. Die anspruchsvollste Variante — weil keine Planung möglich ist, muss das System imstande sein, Lastspitzen autonom zu absorbieren. Erzwingt Stateless-Design, Load-Balancing, horizontale Skalierung und Caching-Layer als zwingenden Architekturbestandteil.

---

*Prägend/Offen-Einschätzung:* Einmaligkeitsprozess ist die offene Antwort — minimale Systemanforderungen. Definiert-gleichzeitig ist moderat prägend: bekannte Grenzen erlauben Planung. Offen-gleichzeitig ist stark prägend: Das System muss elastisch skalieren, ohne zu wissen, wie viele Instanzen kommen.

## Treibende Forces

- Profitorientierung: Zieht offen-gleichzeitige Prozesse für öffentliche Services, weil unbegrenzte Parallelität unbegrenztes Wachstum ermöglicht. Skalierbarkeit ist ein direkter Profithebel im B2C-Bereich.
- Ökologische Nachhaltigkeit: Steht in Spannung zu offen-gleichzeitigen Prozessen — elastische Cloud-Infrastruktur verbraucht bei Lastspitzen viel Energie. Batch-Architekturen (serialisiert, zeitgesteuert) können energieeffizienter sein als permanente Hochverfügbarkeit.
- Ökonomische Nachhaltigkeit: Zieht definiert-gleichzeitige Lösungen, weil planbare Kapazität planbare Betriebskosten bedeutet. Offen-gleichzeitige Architekturen haben schwer kalkulierbare Cloud-Kosten bei Lastspitzen.
- Usability: Offen-gleichzeitige Systeme müssen Wartezeiten und Degradationen bei hoher Last vermeiden — das ist eine direkte Usability-Anforderung. Warteschlangen-Feedback ("Sie sind #48 in der Queue") ist notwendig, wenn Limits erreicht werden.

## Konsequenzen / Propagation nach unten

**Systemebene:**
- Einmaligkeitsprozess: Locking- oder Queue-Mechanismus. Atomare Transaktionen zur Konfliktvermeidung.
- Definiert-gleichzeitig: Resource-Pooling-Element, License-Management, Throttling-Logik, Warteschlangenverwaltung.
- Offen-gleichzeitig: Stateless-Design (Instanzen teilen keinen State), Load-Balancer, Auto-Scaling-Infrastruktur, Caching-Schicht, Datenbank-Sharding oder -Replikation.

**Elementebene:**
- Definiert-gleichzeitig: UI muss bei Kapazitätsgrenze transparente Rückmeldung geben (Warteschlangen-Position, Availability-Status).
- Offen-gleichzeitig: Performance unter Last ist eine zentrale UI-Qualitätsanforderung — kein Einfrieren, keine langen Ladezeiten bei Spitzenlast.

## Verwandte Strukturmuster

- Prozess-Häufigkeit: Prozesse, die sehr häufig laufen (wiederkehrend, Dauerprozess), werden oft offen-gleichzeitig designed. Einmalige Lifecycle-Prozesse können serialisiert werden.
- Prozess-Steuerung: Automatische Prozesse sind oft offen-gleichzeitig oder definiert-gleichzeitig. Menschgeführte Prozesse tendieren zur Serialisierung (ein Mensch entscheidet einmal).
- Medialität: Volldigitale Prozesse können offen-gleichzeitig sein. Analoge Prozesse sind praktisch immer serialisiert — physische Ressourcen (Techniker, Maschinen) begrenzen die Parallelität.

## Beispiele

**NoteMate:** Die Notizen-Synchronisation zwischen Geräten eines einzelnen Nutzers ist faktisch ein Einmaligkeitsprozess pro Nutzer (kein Sync-Konflikt gewünscht). Auf Plattformebene ist es offen-gleichzeitig: Millionen Nutzer synchronisieren gleichzeitig — das System muss elastisch skalieren.

**Familie Heiner:** Der Online-Shop muss offen-gleichzeitig sein — ein viraler Moment oder ein Award könnte Traffic-Spitzen erzeugen. Die Backend-Abrechnung läuft definiert-gleichzeitig: ein Abrechnungslauf pro Nacht, kein paralleles Starten möglich, weil Doppelabrechnung verhindert werden muss.

**Greengineers:** Fernwartungs-Sessions für Techniker sind definiert-gleichzeitig: Die Plattform unterstützt maximal N gleichzeitige Sessions aus Kosten- und Kapazitätsgründen. Der Energy-Dispatch-Service ist offen-gleichzeitig konzipiert — alle Kunden-Haushalte werden gleichzeitig abgefragt.

## Verwendung im Buch

- Kap17#17.4 — Parallelität als vierte Dimension der Geschäftsprozess-Muster

## Verwandt

- Geschäftsprozess — der Baustein der Lösungsebene, den diese Grundgestalt prägt
- Prozess-Häufigkeit — Häufigkeit und Parallelität bestimmen gemeinsam die Infrastrukturanforderungen
- Prozess-Steuerung — Steuerungscharakter beeinflusst, welche Parallelitätsvariante sinnvoll ist
- Medialität — Medialität begrenzt die mögliche Parallelität
- Grundgestalt — die Musterklasse
