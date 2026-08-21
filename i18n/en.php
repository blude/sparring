<?php
declare(strict_types=1);

/**
 * Canonical English string catalog — the reference copy. Every key here
 * must also exist in i18n/de.php (enforced by tests/smoke_i18n.php's
 * catalog-parity check). Flat, dotted keys; full sentences/paragraphs are
 * one key each, with any inline HTML (links, <strong>) baked into the
 * string rather than split at tag boundaries — German word order doesn't
 * let a sentence be reassembled from English-shaped fragments.
 *
 * Not translated (deliberately, not an oversight):
 * - "Sparring" (brand name), the credits.php library-acknowledgement list
 *   (proper nouns + "by <author>", reads fine in either locale).
 * - EN/DE switcher labels — language names shown in their own language is
 *   the universal convention, see config.php's localeSwitcher().
 */

return [
    // --- shared across pages ---
    'common.back' => 'Back',
    'error.404' => "This page doesn't exist or may have moved.",
    'error.500' => 'Something went wrong on our end. Try again in a moment.',
    'error.backToStart' => 'Back to start',

    // --- start.php ---
    'start.title' => 'Sparring — Learn Digital Design',
    'start.tagline' => 'Sparring is a versatile, rigorous partner that challenges you to sharpen your thinking in the emerging discipline of Digital Design.',
    'start.presentedBy' => 'FH DORTMUND and SUPERRAUM presents',
    'start.alphaNotice' => 'Alpha Preview',
    'start.copy.punchline' => 'Prepare your sharpest arguments, throw in your hardest punches, and be ready to take some well-intentioned blows back!',
    'start.cta' => 'Start a new session',
    'start.learnMore' => 'Learn more about <a href="/philosophy">Sparring&rsquo;s philosophy</a>.',
    'start.footer.craft' => '&copy; {year} &middot; Craft with #DigitalMaterial &middot; <a href="/credits">Credits</a>',
    'start.footer.legal' => '<a href="/terms">Terms</a> &middot; <a href="/privacy">Privacy</a> &middot; <a href="/privacy#cookies">Cookies</a>',

    // --- dojo.php (SE-01) + dojo.js ---
    'dojo.title' => 'Dojo — Sparring',
    'dojo.ogDescription' => 'Argue with an AI sparring partner about Digital Design, live.',
    'dojo.avatarPopoverPrefix' => 'You are ',
    'dojo.untitled' => 'Untitled',
    'dojo.endSession' => 'End session',
    'dojo.playbook.heading' => 'Playbook',
    'dojo.playbook.rule1' => 'Start with a provoking position or scenario.',
    'dojo.playbook.rule2' => 'Elaborate your argument in <strong>16 turns or less</strong>.',
    'dojo.playbook.rule3' => 'There&rsquo;s no winning or losing — only progress.',
    'dojo.consent.heading' => "Head's up! Your consent is needed",
    'dojo.consent.tosLine' => 'By taking part in this session you confirm that you have read and understood the <a href="/terms" target="_blank" rel="noopener">Terms of Service</a> and <a href="/privacy" target="_blank" rel="noopener">Privacy Policy</a>.',
    'dojo.consent.participateLabel' => 'I have read the above and agree to take part in the session.',
    'dojo.consent.optionalHeading' => 'Optional — your choice, either or both:',
    'dojo.consent.projectionLabel' => 'I agree that my exchanged messages may be displayed on the projector during the session.',
    'dojo.consent.retentionLabel' => 'I agree that my session may be collected and analyzed for this thesis.',
    'dojo.consent.confirm' => 'Confirm choices',
    'dojo.composer.placeholder' => "What's on your mind?",
    'dojo.composer.sendAriaLabel' => 'Send',
    'dojo.composer.disclaimer' => 'Sparring is AI and can make mistakes',
    'dojo.titleCard.line1' => 'READY?',
    'dojo.titleCard.line2' => 'GET SET',
    'dojo.titleCard.line3' => 'SPAR!',

    'dojo.js.outcomeRateLimited' => 'Too many requests — wait a moment and try again.',
    'dojo.js.outcomeRejected' => 'That message is empty or too long — edit it and try again.',
    'dojo.js.outcomeFlaggedGeneric' => "That message can't be shown here — edit it and try again.",
    'dojo.js.outcomeFlaggedPersonalInfo' => "That message includes personal information and can't be shown here — edit it and try again.",
    'dojo.js.outcomeFlaggedInappropriate' => "That message isn't appropriate for this exhibition — edit it and try again.",
    'dojo.js.outcomeSessionUnknown' => 'This session is no longer available — reload to start a new one.',
    'dojo.js.outcomeGenerationFailed' => 'The installation cannot respond right now — try again.',
    'dojo.js.confirmEndSession' => 'End this session? Your current session will no longer be shown.',
    'dojo.js.sessionComplete' => 'This session has reached its limit — thanks for sparring.',
    'dojo.js.installationUnavailable' => 'The installation is not accepting sessions right now — reload to retry.',
    'dojo.js.consentFailed' => 'Could not record that choice — try again.',
    'dojo.js.thinking' => 'Sparring is thinking…',
    'dojo.js.charsRemaining' => '{n} characters left',

    // --- arena.php (SE-02) ---
    'arena.title' => 'Arena — Sparring',

    // --- credits.php ---
    'credits.title' => 'Credits — Sparring',
    'credits.intro' => 'Sparring is a thesis exhibition piece by Sarah Puppin Pratti.',
    'credits.heading' => 'Credits',
    'credits.builtWith' => "Built with Anthropic's Claude API for response generation and moderation.",
    'credits.duration.heading' => 'Duration of the Exhibition',
    'credits.duration.opening' => 'Opening Ceremony: 4 September 2026, 18:00 TBC',
    'credits.duration.dates' => 'From 4 to 21 September 2026 at SUPERRAUM, Brückstraße 64, 44135 Dortmund',
    'credits.duration.hours' => 'Opening Hours: TBC',
    'credits.author.heading' => 'About the Author',
    'credits.author.bio' => 'Sarah is a designer and researcher based in Dortmund. She is currently a student of the Master Digital Design program at the Fachhochschule Dortmund, where she investigates the role of AI in design education. Her work explores the intersection of technology, creativity, and human-computer interaction.',
    'credits.author.contact' => '<strong>Contact:</strong> For inquiries, please contact <a href="mailto:sarah.puppinpratti001@stud.fh-dortmund.de?subject=Sparring%20Inquiry">sarah.puppinpratti001@stud.fh-dortmund.de</a>',
    'credits.ack.heading' => 'Acknowledgements',
    'credits.ack.intro' => 'Sparring uses the following open-source libraries and technologies:',

    // --- philosophy.php ---
    'philosophy.title' => 'Philosophy — Sparring',
    'philosophy.ogDescription' => 'How Sparring works: an AI partner that challenges your thinking through dialectic sparring in Digital Design.',
    'philosophy.heading' => "Sparring's Philosophy",
    'philosophy.subheading' => 'How Sparring Works',
    'philosophy.body' => 'Sparring is an interactive experience that lets you engage in a simulated sparring session with an AI partner skilled in Digital Design. The partner responds to your arguments in real time, providing a dynamic, engaging intellectual practice. It uses a tailor-made system prompt to identify arguments, generate candidate counter-arguments, and return appropriate responses — creating a realistic, challenging sparring environment.',

    // --- privacy.php ---
    'privacy.title' => 'Privacy Policy — Sparring',
    'privacy.ogDescription' => 'Privacy Policy for the Sparring exhibition piece.',
    'privacy.heading' => 'Privacy Policy',
    'privacy.p1' => 'No account, name, or contact detail is requested. Your session is identified by a random code in the page address, not by you.',
    'privacy.p2' => 'Your exchanges are sent to Anthropic to generate a response. Whether they are shown on the projected wall, and whether they are kept for review after the exhibition, are separate choices you make on the consent screen — you can decline either, or both, and still take part.',
    'privacy.p3' => 'Contributions that appear to contain personal information (a name, contact detail, or a health/financial circumstance) are not shown on the projection regardless of your choices above.',
    'privacy.p4' => 'If you agree that your session may be collected and analyzed for this thesis, the exchanged messages and their timestamps are stored on a server under my control. No other metadata — device, location, or IP address — is retained beyond what is technically necessary to serve the page during your session. Stored sessions are used only for this thesis and any resulting academic publications, are not sold or shared with third parties beyond Anthropic (who processes your input to generate a response, per their own terms), and are analyzed and referenced only in anonymized or aggregate form.',
    'privacy.p5' => 'Stored sessions are kept until the thesis and any related publications are complete, after which they are deleted. You may withdraw your consent to storage at any point during the exhibition by stopping the session; because sessions are not linked to your identity, withdrawal after the exhibition ends is not technically possible, as there is no way to locate your specific session among others.',
    'privacy.cookies.subheading' => '<a name="cookies"></a>Cookies',
    'privacy.cookies.p1' => 'This site uses one cookie, storing your chosen language for up to a year, so it does not have to be re-selected on every visit. No tracking, analytics, or advertising cookies are used.',
    'privacy.contact' => 'For questions about this policy or your data, contact Sarah Puppin-Pratti (<a href="mailto:sarah.puppinpratti001@stud.fh-dortmund.de?subject=Sparring%20Privacy%20Policy">sarah.puppinpratti001@stud.fh-dortmund.de</a>).',

    // --- terms.php ---
    'terms.title' => 'Terms of Service — Sparring',
    'terms.ogDescription' => 'Terms of Service for the Sparring exhibition piece.',
    'terms.heading' => 'Terms of Service',
    'terms.p1' => 'Sparring is an unattended exhibition piece, presented as part of the <strong>Digital Design Semesterausstellung</strong> organized by the Master Digital Design at the Fachhochschule Dortmund from September 4th to 21st 2026 at SUPERRAUM in Dortmund.',
    'terms.p2' => 'By using it you agree that your typed contributions are sent to a third-party language model (Anthropic) to produce a response, and may be shown on the projected wall in this room subject to your own choice on the consent screen.',
    'terms.p3' => 'Participation is voluntary. You may stop at any time by closing the page or walking away, without giving a reason and without consequence.',
    'terms.p4' => "Please do not enter content that is unlawful, or that discloses another person's personal information without their consent. The piece is intended for reflective, good-faith engagement; I reserve the right to end a session or restrict access if it is used in a way that disrupts the exhibition or other participants.",
    'terms.p5' => 'Sparring is a thesis project and not a commercial product. It is offered as-is, without warranty as to availability or continuity of the service during the exhibition period.',
    'terms.privacyLink' => 'See the <a href="/privacy">Privacy Policy</a> for what is stored and for how long.',
];
