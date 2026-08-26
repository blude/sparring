---
name: Arbeitskorb
kurzbeschreibung: Konkretes Aspektmuster der Lösungsebene, bei dem Elemente in einem temporären Sammelkorb gesammelt werden, bevor alle zusammen in einer atomaren Transaktion verarbeitet werden.
typ: muster
klasse: aspektmuster
ebene: loesung
stand: 2026-05-02
---

# Arbeitskorb

> **Hinweis zur Klassifikation (2026-05-02):** Arbeitskorb wurde von *Grundgestalt* zu *Aspektmuster* umklassifiziert. Grund: Es ist ein konkretes Prozessmuster — eine spezifische Ausprägung, keine übergeordnete Grundentscheidung für eine ganze Ebene. Die inhaltliche Ausarbeitung bleibt vollständig gültig.

**Definition:** Der Arbeitskorb ist ein konkretes Aspektmuster auf der Lösungsebene, das einen Geschäftsprozess in zwei klar getrennte Phasen teilt: eine offene Sammelphase, in der der Benutzer Elemente hinzufügt, entfernt und anpasst, und eine Verarbeitungsphase, in der alle Elemente als atomare Einheit übertragen werden.

## Klassifikation

- **Klasse:** Aspektmuster (vormals: Grundgestalt)
- **Ebene:** Lösungsebene
- **Klassifikationsbegründung:** Das Muster bündelt Form (temporäre Sammelstruktur mit Add/Remove-Semantik) und Funktion (Zweiphasiger Ablauf: Sammeln → Atomar verarbeiten) untrennbar. Wer die Arbeitskorb-Form wählt, kauft zwingend die Zweiphasigkeit ein — es gibt keinen Arbeitskorb ohne Commit-Semantik. Das unterscheidet ihn von einer bloßen Aspekt-Topologie der Listenstruktur.
- **Aspekt-Bündelung:** Form: Informationsarchitektur (temporäre Korb-Entität, Session-Kontext); Funktion: Geschäftsprozess (Sammeln → Abschicken als Prozesscharakter)

## Spektrum

Das Muster hat zwei charakteristische Ausprägungen:

**Einfacher Arbeitskorb** (offene Antwort): Der Korb ist temporär und gilt nur für eine Session. Nach Abschluss oder Abbruch verfällt er. Technisch einfach: Session-gebundener State, kein persistentes Speichern nötig. E-Commerce-Warenkorb ohne Login ist das klassische Beispiel.

**Persistenter Arbeitskorb** (prägende Antwort): Der Korb bleibt über Sessions hinaus erhalten. Nutzer können ihn verlassen und zurückkehren. Erzwingt: persistente Korb-Entitäten mit Nutzer-Referenz, Ablauflogik (wie lange bleibt der Korb?), Merging-Logik (was passiert, wenn der Nutzer sich auf einem anderen Gerät einloggt?). Deutlich mehr Architekturkomplexität.

---

*Prägend/Offen-Einschätzung:* Einfacher Korb propagiert begrenzt. Persistenter Korb propagiert stark: Er erzwingt Persistenz-Entitäten, Multi-Device-Abgleich und Ablaufregeln.

## Treibende Forces

- Profitorientierung: Zieht den persistenten Arbeitskorb stark — ein gespeicherter Korb erhöht die Rückkehrrate und damit die Konversionsrate. "Dein Warenkorb wartet noch auf dich" ist ein bewährtes Retargeting-Instrument.
- Usability: Der Arbeitskorb erhöht Usability stark — der Benutzer kann in Ruhe sammeln, ohne jeden Schritt sofort zu finalisieren. Fehler sind leicht korrigierbar, bevor die Transaktion abläuft.
- Ökonomische Nachhaltigkeit: Atomare Transaktionen sind langfristig wartungsärmer als verteilte Schritte. Ein Fehler bei der Verarbeitung betrifft nur einen klar definierten Moment — nicht einen verteilten Zustand.

## Konsequenzen / Propagation nach unten

**Systemebene:**
- Cart-Management-Element mit Session-Handling ist zwingend.
- Validierungs-Logik muss beim Abschicken alle Items prüfen (Verfügbarkeit, Konsistenz).
- Atomare Transaktionslogik: Alle Items werden verarbeitet oder keine. Rollback-Mechanismus bei Fehlern.
- Für persistenten Korb: Persistenz-Schicht, Ablaufregeln, Device-Sync-Logik.

**Elementebene:**
- Add/Remove/Modify-Controls für Korb-Items.
- Visuelles Feedback der aktuellen Sammlung (Anzahl, Gesamtpreis, Übersicht).
- Klarer Commit-Trigger ("Jetzt kaufen", "Absenden", "Konfiguration übertragen").
- Temporäre Korb-Entität in der Datenstruktur; Cart-Item-Entität mit Parent-Referenz.

## Verwandte Strukturmuster

- Flow-Prozess: Der Checkout nach dem Arbeitskorb ist oft als Flow-Prozess gestaltet — die sequenzielle Schrittfolge nach dem Commit. Arbeitskorb und Flow-Prozess sind häufige Partner.
- Prozess-Steuerung: Der Sammelschritt ist manuell (Benutzer entscheidet, was hinein kommt). Der Verarbeitungsschritt nach dem Commit ist häufig systemgeführt oder automatisch.
- Akteure im Prozess: Typischerweise Solo-Prozess während der Sammelphase. Die Verarbeitung kann Multi-Akteur-Prozesse anstoßen (z.B. Lager, Versand).

## Beispiele

**NoteMate:** Mehrere Notizen in einem Projekt werden lokal gesammelt, umgeordnet und angepasst, bevor alles atomar in die Cloud synchronisiert wird. Der "Korb" ist hier implizit — der lokale Änderungs-Cache vor dem Sync.

**Familie Heiner:** Das klassischste Beispiel: Der Einkaufskorb im Online-Shop. Kunde legt Produkte hinein, ändert Mengen, nimmt eins heraus. Erst beim Klick auf "Jetzt bestellen" wird die gesamte Bestellung als eine Transaktion verarbeitet. Scheitert die Zahlung, bleibt der Korb unverändert erhalten.

**Greengineers:** Ein Installationstechniker konfiguriert mehrere Geräte in einer Planungsansicht. Er fügt Geräte hinzu, verändert Parameter, entfernt eines wieder. Erst nach vollständiger Planung sendet er die gesamte Konfiguration atomar an die Cloud — Teilübertragungen würden inkonsistente Zustände erzeugen.

## Verwendung im Buch

- Kap17#17.6.1 — Arbeitskorb als erstes konkretes Prozessmuster

## Verwandt

- Geschäftsprozess — der Baustein der Lösungsebene, den dieses Muster konkretisiert
- Flow-Prozess — häufiger Nachfolgeprozess nach dem Commit
- Prozess-Steuerung — Sammelphase ist manuell, Verarbeitungsphase ist systemgeführt oder automatisch
- Akteure im Prozess — Sammelphase typischerweise Solo
- Grundgestalt — die Musterklasse
