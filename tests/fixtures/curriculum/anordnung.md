---
name: Anordnung
kurzbeschreibung: Grundgestalt der Systemebene — entscheidet, ob die Systemelemente zentral, föderiert oder dezentral angeordnet sind. Prägt Kontrolle, Ausfallverhalten und Latenz.
typ: muster
klasse: grundgestalt
ebene: system
stand: 2026-05-02
---

# Anordnung

**Definition:** Anordnung ist die Grundgestalt der Systemebene, die entscheidet, ob die Systemelemente zentral (ein Knotenpunkt), föderiert (mehrere verbundene Knotenpunkte mit Hierarchie) oder dezentral (gleichrangige verteilte Knoten) angeordnet sind — und damit Kontrolle, Ausfallverhalten, Latenz und Governance-Struktur gleichzeitig prägt.

## Klassifikation

- **Klasse:** Grundgestalt
- **Ebene:** Systemebene
- **Aspekt-Bündelung:** Form: Systemarchitektur (topologische Anordnung der Elemente); Funktion: System Scenario (wie Szenarien bei Knotenausfall, Lastspitzen und Netzwerkpartitionierung laufen)

## Spektrum

| Pol | Charakter | Beispiel |
|---|---|---|
| **Zentral** | Ein primärer Server/Datenzentrum hält alles — einfach, kontrollierbar, Single-Point-of-Failure | Klassische Web-App auf einem Cloud-Server, zentraler LDAP-Server |
| **Föderiert** | Mehrere Knoten mit klarer Hierarchie oder Zuständigkeitsregeln — verbunden, aber dezentral verantwortlich | E-Mail-Infrastruktur (eigene Mailserver + Gateway), Banken-Clearinghouse |
| **Dezentral** | Gleichrangige Knoten ohne zentralen Koordinator — maximale Ausfallresilienz, aber Konsistenzprobleme | Blockchain, P2P-Netzwerke, Greengineers-Homeserver-Verbund |

**Offene Antwort (zentral):** Einfachste Architektur. Alle Daten und Logik an einem Ort. Problemlos zu debuggen und zu updaten. Single-Point-of-Failure als inhärentes Risiko.

**Prägende Antworten (föderiert und dezentral):** Föderiert erzwingt Konsistenz-Protokolle zwischen Knoten. Dezentral erzwingt Konsens-Algorithmen (oder akzeptiert Eventual Consistency). Beide erhöhen die Architekturkomplexität erheblich.

**Hybrid als bewusste Wahl:** "Föderiert" ist in gewissem Sinne schon ein Hybrid — eine Zwischenstufe zwischen zentraler Kontrolle und voller Dezentralisierung. Wer Hybrid zwischen zentral und dezentral wählt, muss die Aufteilung explizieren: Was bleibt zentral koordiniert, was läuft lokal?

## Wesentlichkeits-Linse

*Wo entsteht die wesentliche Verarbeitungslast?* Edge-Computing-Architekturen sind dezentral in der Verarbeitung, aber oft zentral in der Aggregation. Die Wesentlichkeit entscheidet, welcher Pol dominiert.

## Treibende Forces

- Souveränität: Zieht föderiert oder dezentral — keine Abhängigkeit von einem zentralen Anbieter; Verteilung als Machtverzichtsmechanismus.
- Freiheit: Zieht dezentrale Architekturen — kein zentraler Gatekeeper, der Optionsräume kontrolliert.
- Ökologische Nachhaltigkeit: Kann dezentral bevorzugen (lokale Verarbeitung spart Übertragungsenergie); aber auch zentral (optimierte Rechenzentren effizienter als viele kleine Knoten).
- Vertrauenswürdigkeit: Zieht föderierte oder dezentrale Architekturen mit Redundanz für Ausfallresilienz; kann aber auch zentral bevorzugen (ein klar verantwortlicher Knoten ist leichter zu sichern und zu auditieren).
- Profitorientierung: Zieht oft zentrale Architekturen — einfacher, günstiger zu betreiben, einfacher skalierbar; zentrale Kontrolle erleichtert Monetarisierung.
- Selbstbestimmung: Zieht dezentrale Architekturen — Daten bleiben lokal, kein zentraler Datenspeicher ohne explizite Zustimmung.
- Marktbeherrschung: Zieht zentrale Architekturen — ein zentraler Kontrollpunkt ermöglicht vollständige Kontrolle über Plattformzugang und Datenflüsse.
- Solidarität: Zieht föderierte oder dezentrale Strukturen — keine asymmetrische Machtkonzentration bei einem Akteur.
- Unterhaltung: Zieht dezentrale oder föderierte Anordnungen für Multiplayer-Szenarien — verteilte Verarbeitung für niedrige Latenz in Echtzeit-Interaktionen.

## Konsequenzen / Propagation nach unten

**Systemebene (interne Konsequenzen):**
- Dezentral/föderiert → Konsistenz-Management zwingend (CAP-Theorem-Kompromisse explizit)
- Dezentral → Netzwerkpartitionierung muss als Normalzustand behandelt werden (nicht als Ausnahme)
- Zentral → klare Failover-Strategie für den Single-Point-of-Failure

**Elementebene:**
- Dezentral → lokale Datenspeicherung als primärer Ort; Sync nur wenn verbunden
- Zentral → remote Datenspeicherung als primärer Ort; lokale Caches nur optional

## Verwandte Strukturmuster

- Verfügbarkeitsannahme: Online-First passt zu zentraler Anordnung; Offline-First zu dezentraler
- Hardware-Hoheit: Eigene Hardware tendiert zu dezentraler/föderierter Anordnung
- Benutzerzugang: Dezentrale Systeme haben oft offenere Benutzerzugänge

## Beispiele

**NoteMate** (zentral mit Replikation): Ein primärer Cloud-Datenspeicher, global repliziert für Latenz. Keine echte Dezentralisierung — Kontrolle liegt beim Anbieter.

**Familie Heiner** (zentral): Shop-Server zentral. Keine Notwendigkeit für Verteilung — Skalierungsanforderungen gering.

**Greengineers** (föderiert): Homeserver lokal (dezentrale Verarbeitung) + Cloud-Plattform zentral (Aggregation, Optimierung). Klassisches föderiertes Muster: Verarbeitung nah am Gerät, Intelligenz in der Cloud.
