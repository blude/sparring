---
name: Akteur
kurzbeschreibung: Eine Rolle in einem Geschäftsprozess auf der Lösungsebene — ausgeführt von einem Menschen oder einem digitalen Element.
typ: konzept
stand: 2026-04-26
---

# Akteur

**Definition:** Ein Akteur ist eine Rolle in einem [[geschaeftsprozess]] auf der [[loesungsebene]] — ausgeführt von einem Menschen oder einem [[digitales-element|digitalen Element]]. Akteure handeln; sie sind nicht passive Stakeholder.

## Erläuterung

Akteure sind die Handlungsträger in [[geschaeftsprozess|Geschäftsprozessen]]. Sie führen Handlungsschritte aus, lösen Aktionen aus, reagieren auf Ereignisse. Dabei können Akteure sein:

- **Menschen** aus dem [[kundenkontext]] (Kundinnen, Kunden)
- **Rollen** aus der [[aufbauorganisation]] (Mitarbeitende, Shop-Managerin, Fahrer)
- **Ganze Organisationen**, wenn [[partner]] als eigenständige Einheiten im Prozess handeln
- **[[digitales-element|Digitale Elemente]]**, wenn sie im Prozess eigenständig handeln — z. B. die Entscheidungs-Engine bei Greengineers, die Steuerungsbefehle auf Basis von Daten erzeugt

Diese Breite ist bewusst: In einer digitalen Lösung sind Prozesse nicht nur menschlich. Technische Systeme sind echte Akteure — sie handeln, treffen Entscheidungen, lösen Folgeschritte aus.

Akteure sind an die [[wertschoepfungsarchitektur]] gebunden (Haftungsregel 3 der [[loesungsebene]]): Im Geschäftsprozess dürfen nur Akteure auftreten, die in der Wertschöpfungsarchitektur definiert sind. Umgekehrt sollte jeder in der Wertschöpfungsarchitektur definierte Akteur in mindestens einem Geschäftsprozess eine Rolle spielen.

## Abgrenzung

- **Akteur ≠ [[stakeholder]].** Stakeholder sind an der Lösung interessiert oder von ihr betroffen — ohne notwendigerweise selbst zu handeln. Ein Akteur handelt. Gesetzgeber und Investoren sind Stakeholder, keine Akteure.
- **Akteur ≠ [[benutzertyp]].** Der Benutzertyp ist eine Konkretisierung auf der [[systemebene]]: Menschen, die mit dem technischen System interagieren. Ein Akteur kann auf der Lösungsebene auch ein digitales Element sein, das im Geschäftsprozess handelt — kein Mensch. Die Fahrer-App ist kein Benutzertyp, aber ein technischer Akteur im Lieferprozess von Familie Heiner.
- **Akteur ≠ Person.** Ein Akteur ist eine *Rolle*, nicht eine spezifische Person. Mehrere Menschen können dieselbe Akteur-Rolle einnehmen. Eine Person kann in verschiedenen Prozessen verschiedene Akteur-Rollen übernehmen.

## Beispiele

**Familie Heiner:**
- Menschliche Akteure: Kunde (gibt Bestellung auf), Mitarbeiter (packt Sendung), Fahrer (liefert aus)
- Technische Akteure: Online-Shop (nimmt Bestellung entgegen, prüft Verfügbarkeit), Warenwirtschaft (aktualisiert Lagerbestand), Fahrer-App (optimiert Route, bestätigt Zustellung)

**Greengineers:**
- Technischer Akteur: Die Entscheidungs-Engine agiert eigenständig — sie liest Strompreise und Wetterdaten, berechnet die optimale Heizstrategie und sendet Steuerungsbefehle. Kein Mensch ist in der Hauptlinie dieses Prozesses beteiligt.
- Menschlicher Akteur: Hausbesitzer (setzt Präferenzen, prüft Ersparnisse im Portal)

## Verwendung im Buch

- [[Kap03#3.2.3]] führt Akteure im Kontext der Geschäftsprozesse ein.
- [[Kap03#3.2.6]] Regel 3: Geschäftsprozesse verwenden nur Akteure der Wertschöpfungsarchitektur.
- [[Kap15]] vertieft Akteure als eigenes Teil-II-Muster auf der Lösungsebene.

## Verwandt

- [[loesungsebene]] — die Heimatebene des Begriffs
- [[geschaeftsprozess]] — der Ort, an dem Akteure handeln
- [[stakeholder]] — Oberbegriff; Akteur ist eine handelnde Stakeholder-Rolle
- [[benutzertyp]] — Konkretisierung menschlicher Akteure auf der Systemebene
- [[aufbauorganisation]] — liefert die internen menschlichen Akteure
- [[digitales-element]] — liefert die technischen Akteure
