<?php
declare(strict_types=1);

/**
 * German string catalog. Machine-drafted (Claude) against i18n/en.php's
 * key set — enforced by tests/smoke_i18n.php's catalog-parity check.
 * General UI copy below is usable as-is; the privacy.* / terms.* section
 * carries its own review flag (see that section's comment) since legal
 * text warrants a closer look than UI chrome does.
 */

return [
    // --- shared across pages ---
    'common.back' => 'Zurück',
    'error.404' => 'Diese Seite existiert nicht oder wurde verschoben.',
    'error.500' => 'Bei uns ist etwas schiefgelaufen. Versuch es gleich noch einmal.',
    'error.backToStart' => 'Zurück zum Start',

    // --- start.php ---
    'start.title' => 'Sparring — Digital Design lernen',
    'start.tagline' => 'Sparring ist ein vielseitiger, anspruchsvoller Partner, der dich herausfordert, dein Denken in der aufstrebenden Disziplin Digital Design zu schärfen.',
    'start.presentedBy' => 'FH DORTMUND und SUPERRAUM präsentieren',
    'start.alphaNotice' => 'Alpha-Vorschau',
    'start.copy.punchline' => 'Bereite deine schärfsten Argumente vor, teile deine härtesten Schläge aus und sei bereit, wohlmeinende Konter einzustecken!',
    'start.cta' => 'Neue Sitzung starten',
    'start.learnMore' => 'Erfahre mehr über die <a href="/philosophy">Philosophie von Sparring</a>.',
    'start.footer.craft' => '&copy; {year} &middot; Craft mit #DigitalMaterial &middot; <a href="/credits">Credits</a>',
    'start.footer.legal' => '<a href="/terms">AGB</a> &middot; <a href="/privacy">Datenschutz</a> &middot; <a href="/privacy#cookies">Cookies</a>',

    // --- dojo.php (SE-01) + dojo.js ---
    'dojo.title' => 'Dojo — Sparring',
    'dojo.ogDescription' => 'Streite live mit einem KI-Sparringpartner über Digital Design.',
    'dojo.avatarPopoverPrefix' => 'Du bist ',
    'dojo.untitled' => 'Unbenannt',
    'dojo.endSession' => 'Sitzung beenden',
    'dojo.playbook.heading' => 'Spielregeln',
    'dojo.playbook.rule1' => 'Beginne mit einer provokanten Position oder einem Szenario.',
    'dojo.playbook.rule2' => 'Führe dein Argument in <strong>höchstens 16 Zügen</strong> aus.',
    'dojo.playbook.rule3' => 'Es gibt kein Gewinnen oder Verlieren — nur Fortschritt.',
    'dojo.consent.heading' => 'Kurzer Hinweis: Wir brauchen deine Zustimmung',
    'dojo.consent.tosLine' => 'Mit deiner Teilnahme an dieser Sitzung bestätigst du, dass du die <a href="/terms" target="_blank" rel="noopener">Nutzungsbedingungen</a> und die <a href="/privacy" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und verstanden hast.',
    'dojo.consent.participateLabel' => 'Ich habe das Obenstehende gelesen und stimme der Teilnahme an der Sitzung zu.',
    'dojo.consent.optionalHeading' => 'Optional — deine Wahl, beides oder nur eines:',
    'dojo.consent.projectionLabel' => 'Ich bin einverstanden, dass meine ausgetauschten Nachrichten während der Sitzung auf dem Projektor angezeigt werden dürfen.',
    'dojo.consent.retentionLabel' => 'Ich bin einverstanden, dass meine Sitzung für diese Abschlussarbeit gesammelt und ausgewertet werden darf.',
    'dojo.consent.confirm' => 'Auswahl bestätigen',
    'dojo.composer.placeholder' => 'Was beschäftigt dich?',
    'dojo.composer.sendAriaLabel' => 'Senden',
    'dojo.composer.disclaimer' => 'Sparring ist KI und kann Fehler machen',
    'dojo.titleCard.line1' => 'BEREIT?',
    'dojo.titleCard.line2' => 'FERTIG',
    'dojo.titleCard.line3' => 'SPARR!',

    'dojo.js.outcomeRateLimited' => 'Zu viele Anfragen — warte einen Moment und versuch es erneut.',
    'dojo.js.outcomeRejected' => 'Diese Nachricht ist leer oder zu lang — bearbeite sie und versuch es erneut.',
    'dojo.js.outcomeFlaggedGeneric' => 'Diese Nachricht kann hier nicht angezeigt werden — bearbeite sie und versuch es erneut.',
    'dojo.js.outcomeFlaggedPersonalInfo' => 'Diese Nachricht enthält persönliche Informationen und kann hier nicht angezeigt werden — bearbeite sie und versuch es erneut.',
    'dojo.js.outcomeFlaggedInappropriate' => 'Diese Nachricht ist für diese Ausstellung nicht angemessen — bearbeite sie und versuch es erneut.',
    'dojo.js.outcomeSessionUnknown' => 'Diese Sitzung ist nicht mehr verfügbar — lade die Seite neu, um eine neue zu starten.',
    'dojo.js.outcomeGenerationFailed' => 'Die Installation kann gerade nicht antworten — versuch es erneut.',
    'dojo.js.confirmEndSession' => 'Diese Sitzung beenden? Deine aktuelle Sitzung wird nicht mehr angezeigt.',
    'dojo.js.sessionComplete' => 'Diese Sitzung hat ihr Limit erreicht — danke fürs Sparring.',
    'dojo.js.installationUnavailable' => 'Die Installation nimmt gerade keine Sitzungen an — lade die Seite neu, um es erneut zu versuchen.',
    'dojo.js.consentFailed' => 'Diese Auswahl konnte nicht gespeichert werden — versuch es erneut.',
    'dojo.js.thinking' => 'Sparring denkt nach…',
    'dojo.js.charsRemaining' => 'Noch {n} Zeichen', // German word order: count doesn't lead the sentence like the English "N characters left"

    // --- arena.php (SE-02) ---
    'arena.title' => 'Arena — Sparring',

    // --- credits.php ---
    'credits.title' => 'Credits — Sparring',
    'credits.intro' => 'Sparring ist eine Abschlussarbeit-Ausstellung von Sarah Puppin Pratti.',
    'credits.heading' => 'Credits',
    'credits.builtWith' => 'Erstellt mit Anthropics Claude API für Antwortgenerierung und Moderation.',
    'credits.duration.heading' => 'Dauer der Ausstellung',
    'credits.duration.opening' => 'Eröffnungsfeier: 4. September 2026, 18:00 Uhr (vorläufig)',
    'credits.duration.dates' => 'Vom 4. bis 21. September 2026 im SUPERRAUM, Brückstraße 64, 44135 Dortmund',
    'credits.duration.hours' => 'Öffnungszeiten: noch offen',
    'credits.author.heading' => 'Über die Autorin',
    'credits.author.bio' => 'Sarah ist Designerin und Forscherin mit Sitz in Dortmund. Sie studiert derzeit im Masterstudiengang Digital Design an der Fachhochschule Dortmund, wo sie die Rolle von KI in der Designausbildung untersucht. Ihre Arbeit erforscht die Schnittstelle von Technologie, Kreativität und Mensch-Computer-Interaktion.',
    'credits.author.contact' => '<strong>Kontakt:</strong> Für Anfragen wende dich bitte an <a href="mailto:sarah.puppinpratti001@stud.fh-dortmund.de?subject=Sparring%20Inquiry">sarah.puppinpratti001@stud.fh-dortmund.de</a>',
    'credits.ack.heading' => 'Danksagungen',
    'credits.ack.intro' => 'Sparring verwendet die folgenden Open-Source-Bibliotheken und -Technologien:',

    // --- philosophy.php ---
    'philosophy.title' => 'Philosophie — Sparring',
    'philosophy.ogDescription' => 'So funktioniert Sparring: ein KI-Partner, der dein Denken durch dialektisches Sparring im Digital Design herausfordert.',
    'philosophy.heading' => 'Die Philosophie von Sparring',
    'philosophy.subheading' => 'Wie Sparring funktioniert',
    'philosophy.body' => 'Sparring ist eine interaktive Erfahrung, bei der du dich auf eine simulierte Sparring-Sitzung mit einem im Digital Design versierten KI-Partner einlässt. Der Partner reagiert in Echtzeit auf deine Argumente und ermöglicht so eine dynamische, ansprechende intellektuelle Übung. Er nutzt einen maßgeschneiderten System-Prompt, um Argumente zu erkennen, mögliche Gegenargumente zu erzeugen und passende Antworten zurückzugeben — so entsteht eine realistische, herausfordernde Sparring-Umgebung.',

    // --- privacy.php / terms.php ---
    // ponytail: machine-translated (Claude), not reviewed by a native
    // speaker or legal counsel — verify before treating as an authoritative
    // German Privacy Policy / Terms of Service.
    'privacy.title' => 'Datenschutzerklärung — Sparring',
    'privacy.ogDescription' => 'Datenschutzerklärung für das Ausstellungsstück Sparring.',
    'privacy.heading' => 'Datenschutzerklärung',
    'privacy.p1' => 'Es wird kein Konto, kein Name und keine Kontaktangabe verlangt. Deine Sitzung wird durch einen zufälligen Code in der Seitenadresse identifiziert, nicht durch dich.',
    'privacy.p2' => 'Deine Nachrichten werden an Anthropic gesendet, um eine Antwort zu erzeugen. Ob sie auf der projizierten Wand gezeigt und ob sie nach der Ausstellung zur Auswertung aufbewahrt werden, sind getrennte Entscheidungen, die du auf dem Zustimmungsbildschirm triffst — du kannst beides oder eines davon ablehnen und trotzdem teilnehmen.',
    'privacy.p3' => 'Beiträge, die persönliche Informationen zu enthalten scheinen (ein Name, eine Kontaktangabe oder gesundheitliche/finanzielle Umstände), werden unabhängig von deinen obigen Entscheidungen nicht auf der Projektion gezeigt.',
    'privacy.p4' => 'Wenn du zustimmst, dass deine Sitzung für diese Abschlussarbeit gesammelt und ausgewertet werden darf, werden die ausgetauschten Nachrichten und ihre Zeitstempel auf einem Server unter meiner Kontrolle gespeichert. Es werden keine weiteren Metadaten — Gerät, Standort oder IP-Adresse — über das technisch Notwendige zur Bereitstellung der Seite während deiner Sitzung hinaus gespeichert. Gespeicherte Sitzungen werden ausschließlich für diese Abschlussarbeit und daraus resultierende wissenschaftliche Veröffentlichungen verwendet, nicht verkauft oder an Dritte außer Anthropic weitergegeben (das deine Eingaben gemäß seinen eigenen Bedingungen verarbeitet, um eine Antwort zu erzeugen), und werden nur anonymisiert oder aggregiert analysiert und referenziert.',
    'privacy.p5' => 'Gespeicherte Sitzungen werden aufbewahrt, bis die Abschlussarbeit und etwaige zugehörige Veröffentlichungen abgeschlossen sind, und danach gelöscht. Du kannst deine Zustimmung zur Speicherung jederzeit während der Ausstellung widerrufen, indem du die Sitzung beendest; da Sitzungen nicht mit deiner Identität verknüpft sind, ist ein Widerruf nach Ende der Ausstellung technisch nicht möglich, da deine spezifische Sitzung unter anderen nicht auffindbar ist.',
    'privacy.cookies.subheading' => '<a name="cookies"></a>Cookies',
    'privacy.cookies.p1' => 'Diese Seite verwendet ein einziges Cookie, das deine gewählte Sprache für bis zu ein Jahr speichert, damit sie nicht bei jedem Besuch erneut ausgewählt werden muss. Es werden keine Tracking-, Analyse- oder Werbe-Cookies eingesetzt.',
    'privacy.contact' => 'Bei Fragen zu dieser Erklärung oder deinen Daten wende dich an Sarah Puppin-Pratti (<a href="mailto:sarah.puppinpratti001@stud.fh-dortmund.de?subject=Sparring%20Privacy%20Policy">sarah.puppinpratti001@stud.fh-dortmund.de</a>).',

    'terms.title' => 'Nutzungsbedingungen — Sparring',
    'terms.ogDescription' => 'Nutzungsbedingungen für das Ausstellungsstück Sparring.',
    'terms.heading' => 'Nutzungsbedingungen',
    'terms.p1' => 'Sparring ist ein unbeaufsichtigtes Ausstellungsstück im Rahmen der <strong>Digital Design Semesterausstellung</strong>, organisiert vom Masterstudiengang Digital Design der Fachhochschule Dortmund, vom 4. bis 21. September 2026 im SUPERRAUM in Dortmund.',
    'terms.p2' => 'Mit der Nutzung stimmst du zu, dass deine eingegebenen Beiträge an ein Sprachmodell eines Drittanbieters (Anthropic) gesendet werden, um eine Antwort zu erzeugen, und je nach deiner eigenen Wahl auf dem Zustimmungsbildschirm auf der projizierten Wand in diesem Raum gezeigt werden können.',
    'terms.p3' => 'Die Teilnahme ist freiwillig. Du kannst jederzeit aufhören, indem du die Seite schließt oder weggehst, ohne einen Grund anzugeben und ohne Konsequenzen.',
    'terms.p4' => 'Bitte gib keine Inhalte ein, die rechtswidrig sind oder die persönlichen Informationen einer anderen Person ohne deren Zustimmung offenlegen. Das Stück ist für reflektiertes Engagement in gutem Glauben gedacht; ich behalte mir das Recht vor, eine Sitzung zu beenden oder den Zugang einzuschränken, wenn sie in einer Weise genutzt wird, die die Ausstellung oder andere Teilnehmende stört.',
    'terms.p5' => 'Sparring ist ein Abschlussarbeitsprojekt und kein kommerzielles Produkt. Es wird ohne Gewähr für Verfügbarkeit oder Kontinuität des Dienstes während des Ausstellungszeitraums angeboten.',
    'terms.privacyLink' => 'Siehe die <a href="/privacy">Datenschutzerklärung</a> für Details, was gespeichert wird und wie lange.',
];
