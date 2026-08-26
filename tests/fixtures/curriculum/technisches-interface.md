---
name: Technisches Interface
kurzbeschreibung: Die präzise Beschreibung der Kommunikation zwischen einem Element und seiner Außenwelt.
typ: konzept
stand: 2026-04-26
---

# Technisches Interface

**Definition:** Die präzise Beschreibung der Kommunikation zwischen einem Element und seiner Außenwelt. Ein technisches Interface definiert Eingaben, Ausgaben und die Richtung (inbound / outbound) — technologieneutral, ohne Protokolle oder Datenformate festzulegen.

## Erläuterung

Das technische Interface ist Teil der **verborgenen Form** eines Elements auf der Elementebene. Während die Systemebene definiert, *dass* und *zwischen wem* kommuniziert wird, beschreibt das technische Interface *was genau* fließt — und wohin es im Element geht.

Zwei Richtungen:

- **Inbound** (von außen ins Element): Das Element empfängt Daten oder eine Anfrage. Was kommt herein? Welche Attribute werden erwartet? Was passiert mit den Daten — fließen sie in einen Use Case, werden sie direkt gespeichert, oder beides?
- **Outbound** (vom Element nach außen): Das Element sendet Daten oder eine Anfrage. Was geht hinaus? Welche Attribute werden übergeben? Wozu dient die Kommunikation?

**Gestaltungsentscheidung bei zwei eigenen Elementen:** Wo wird ein Interface zwischen zwei eigenen Elementen definiert — als inbound beim Empfänger oder als outbound beim Sender? Es gibt kein Dogma. Bei Verbindungen zu **vorhandenen Elementen** (Partnersysteme, eingekaufte Infrastruktur) ist die Antwort klar: outbound am eigenen Element, weil das vorhandene Element nicht Teil des Entwurfs ist. Bei Verbindungen zwischen **zwei eigenen Elementen** ist es eine Entwurfsentscheidung — die inbound-Seite vermeidet Redundanz; die outbound-Seite kann bei Adapter-Mustern sinnvoll sein. Konkrete Techniken gehören in Kap06 (Entwurfstechniken) und Kap19 (Schnittstellen-Muster).

Das technische Interface bleibt bewusst **technologieneutral**: Ob die Übertragung über eine REST-API, eine Message-Queue oder einen anderen Mechanismus geschieht, ist eine Realisierungsentscheidung (vgl. Ebenensprache).

## Abgrenzung

- **Technisches Interface ≠ Schnittstelle auf der Systemebene.** Auf der Systemebene entsteht eine Schnittstelle aus der Beziehung zwischen Elementen — sie zeigt, dass Kommunikation stattfindet. Das technische Interface auf der Elementebene *präzisiert*, was genau fließt.
- **Technisches Interface ≠ Protokoll oder API-Spezifikation.** Diese gehören in den Realisierungssprech. Das technische Interface benennt Attribute und Richtung, nicht Datenformate oder Übertragungsdetails.

## Beispiele

**Familie Heiner — Bestellungs-Backend:**
- Inbound vom Shop-Frontend: empfängt Bestelldaten (Produkte, Mengen, Lieferadresse, Kundendaten) → fließt in Use Case „Bestellung aufgeben".
- Outbound zum Lager-System: sendet Produkt-Referenz und Menge; empfängt Verfügbarkeitsstatus. Zeitkritisch (< 2 Sekunden auf Systemebene definiert).
- Outbound zum Payment-Provider: sendet Bestellbetrag und Zahlungsmethode; empfängt Zahlungsstatus.

## Verwendung im Buch

- Kap03#3.4.2 führt das technische Interface als verborgene Verbindung nach außen ein.
- Kap06 vertieft Entwurfstechniken für Interfaces.
- Kap19 behandelt Schnittstellen-Muster.

## Verwandt

- Elementebene — die Ebene, auf der das technische Interface beheimatet ist
- User Interface · Entität · Physischer Aufbau · Use Case · Technische Funktion — die anderen Bausteine der Elementebene
- Systemarchitektur — die Systemebene, aus der die Schnittstellenbeziehungen stammen
- Konsistenzregel — Haftungsregeln, die technische Interfaces an Use Cases und Entitäten binden
