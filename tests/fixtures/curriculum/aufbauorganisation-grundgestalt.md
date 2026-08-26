---
name: Aufbauorganisation-Grundgestalt
kurzbeschreibung: Grundgestalt der Lösungsebene — entscheidet, wer die Wertschöpfung erbringt: eigene Organisation, Partner oder ein Hybrid mit klarer Aufteilung.
typ: muster
klasse: grundgestalt
ebene: loesung
stand: 2026-05-02
---

# Aufbauorganisation-Grundgestalt

> **Namenskonvention:** Diese Seite trägt den Suffix `-grundgestalt`, weil Aufbauorganisation als Begriffsseite bereits existiert.

**Definition:** Aufbauorganisation-Grundgestalt ist die Grundgestalt der Lösungsebene, die entscheidet, wer die wesentliche Wertschöpfung der Lösung erbringt — die eigene Organisation souverän, ein Netzwerk von Partnern oder eine bewusste Hybridstruktur mit klar geregelter Aufteilung.

## Klassifikation

- **Klasse:** Grundgestalt
- **Ebene:** Lösungsebene
- **Aspekt-Bündelung:** Form: Wertschöpfungsarchitektur / Aufbauorganisation (wer ist strukturell involviert); Funktion: Geschäftsprozess (wie und durch wen werden Prozesse ausgeführt)

## Spektrum

| Pol | Charakter | Beispiel |
|---|---|---|
| **Souverän** | Alle wesentlichen Leistungen werden von der eigenen Organisation erbracht | Kleines SaaS-Startup, internes Unternehmenssystem |
| **Hybrid** | Eigene Organisation erbringt Kernleistungen, Partner erbringen Ergänzungsleistungen — klare Aufteilung | NoteMate mit Cloud-Infrastruktur-Partner; Heiner mit Logistikdienstleister |
| **Partnerorientiert** | Die eigene Organisation koordiniert, Partner erbringen die wesentliche Wertschöpfung | Ökosystem-Plattform (Amazon), Franchise-Modell, Kooperative |

**Offene Antwort (souverän):** Maximale Kontrolle, maximaler Aufwand. Keine externen Abhängigkeiten — aber auch keine Skalierungseffekte durch Partner. Systemarchitektur ist "Build" (→ Entwicklung (Grundgestalt)).

**Prägende Antwort (hybrid):** Partner werden zu strukturellen Bausteinen der Lösung. Die Grenze zwischen "eigener Leistung" und "Partner-Leistung" muss explizit geregelt sein. Erzwingt Vertrags-, Schnittstellen- und Governance-Architektur.

**Prägende Antwort (partnerorientiert):** Die eigene Organisation ist Koordinator, nicht primärer Leistungserbringer. Das erzwingt Plattform-Architekturen, Onboarding-Infrastruktur für Partner und klar definierte Governance-Regeln. Stark korreliert mit Ökosystem-Wertversprechen.

**Hybrid als bewusste Wahl:** Wer Hybrid wählt, muss die Aufteilung explizit machen: Welche Leistungen bleiben souverän? Welche werden Partner übertragen? Welche Schnittstellen definieren die Grenze?

## Wesentlichkeits-Linse

*Wo liegt die wesentliche Wertschöpfung?* Diese Frage schärft die Einordnung. Wenn 80 % des Werts durch Partner entsteht, ist die Lösung partnerorientiert — auch wenn die eigene Organisation noch viele Nebenleistungen erbringt.

## Treibende Forces

- Souveränität: Zieht souveräne Aufbauorganisation — keine wesentliche Abhängigkeit von einzelnen externen Partnern, die kritische Wertschöpfung kontrollieren.
- Ökonomische Nachhaltigkeit: Kann beide Pole ziehen: Eigenleistung für langfristige Stabilität und Kernkompetenz-Aufbau, Partner für Kosteneffizienz in nicht-differenzierenden Bereichen.
- Profitorientierung: Zieht Hybrid und partnerorientiert — Skalierung durch Partner ohne proportionale Fixkostenerhöhung verbessert das Ertragsprofil.
- Solidarität: Zieht kooperative und genossenschaftliche Strukturen — Wertschöpfung wird gemeinschaftlich erbracht und geteilt statt extrahiert.
- Soziale Nachhaltigkeit: Zieht hybride und kooperative Strukturen — faire Wertverteilung in der Wertschöpfungskette als Entwurfsziel; Arbeitsbedingungen der Leistungserbringer als Gestaltungsraum.
- Freiheit: Steht in Spannung zu partnerorientierter Aufbauorganisation mit asymmetrischer Machtverteilung — Freiheit bevorzugt symmetrische oder souveräne Strukturen.
- Marktbeherrschung: Zieht partnerorientierte Aufbauorganisation mit asymmetrischer Machtverteilung — der Plattformbetreiber koordiniert, Partner erbringen Leistung unter den Bedingungen des Betreibers.

## Konsequenzen / Propagation nach unten

**Systemebene:**
- Souverän → Build-Orientierung (Entwicklung (Grundgestalt): Eigenentwicklung)
- Hybrid → gemischte Build/Rely-Entscheidungen; technische Schnittstellen zu Partnersystemen
- Partnerorientiert → API-First-Architektur, Onboarding-Infrastruktur, mandantenfähige Systeme

**Lösungsebene:**
- Partnerorientiert ist eng verknüpft mit indirektem Wertversprechen (Wertversprechen-Charakter)

## Verwandte Strukturmuster

- Entwicklung (Grundgestalt): Aufbauorganisation prägt die Build/Rely-Entscheidung auf Systemebene direkt
- Wertversprechen-Charakter: Partnerorientiert → häufig indirektes Wertversprechen
- Digitales Ökosystem: Partnerorientierte Aufbauorganisation auf Plattform-Ebene

## Beispiele

**NoteMate** (hybrid): Kern-Engineering souverän, Cloud-Infrastruktur als verlässlicher Partner (AWS/GCP), App-Store-Distribution als weiterer Partner. Klare Aufteilung: Was baut NoteMate selbst, was bezieht es von außen?

**Familie Heiner** (hybrid bis souverän): Bestellung, Ernte, Verpackung souverän. Lieferlogistik durch externen Dienstleister (Partner). Finanzdienstleistungen durch Bank/Payment-Anbieter. Die Kern-Wertschöpfung (Bio-Produkte, persönliche Beziehung) bleibt souverän.

**Greengineers** (partnerorientiert): Greengineers koordiniert — Installateure sind Partner, Energieversorger sind Partner, Gerätehersteller sind Partner. Die eigene Organisation erbringt Plattform-Betrieb, Datenanalyse und Koordination. Die Wertschöpfung am Haus entsteht durch das Partnernetz.
