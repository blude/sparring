# To Do

## New Features

- [ ] Add support for invoking a starting scenario through a query parameter
  - Allows different QR Codes to be generated, each one containing URLs pointing to a different opening scenario
  - Naturally, visitor has to accept terms and conditions first.
  - Query parameter only sends a scenario scenario ID.
  - The Scenario ID and message pairs are mapped in code. 
  - First message is sent automatically with "Sparring Scenario: {Scenario Description}"
- [ ] update sparring.md prompt (blocked, waiting for instructions)
- [ ] update moderation.md prompt (blocked, waiting for instructions)

### Input

- [ ] Add support for the user to export their session's conversation (initially JSON).
- [ ] Add support for rendering mermaid diagrams

### Admin Screen (new)

- [ ] Add admin screen where sessions and exchanges are listed.
- [ ] Avaiable actions
  - [ ] Delete session
  - [ ] Mark session as unsuitable for display
  - [ ] Edit exchange (all attributes)
  - [ ] Reset database
  - [ ] Import data (from pilot seed)
- [ ] Simple password protected (to start)

## Improvements

### Input

- [ ] Change the visual style of the Sparring partner's message, so that the bubble is not visible anymore and text size is a bit larger.
- [ ] Show status "Sparring is thinking…" inline along with chat history
  - Status message is then replaced by the incoming message.

### Display

#### Later

- [ ] Never show the first exchange of a session, if user message starts with "Sparring Scenario:".
  - Rationale: This first exchange doesn't deliver friction.
- [ ] Implement playful design for displaying exchanges (waiting for mockup).
- [ ] Add support for rendering Mermaid diagrams.
- [ ] Display AI generated session summaries (Using Haiku possibly)

### Sparring System Prompt

- [ ] Initial Scenario Setup
  - Description: If first visitor message starts with "Sparring Scenario:" then the immediate Sparring response is a simple acknowledgment of the scenario. 
- [ ] Add support for generating Mermaid diagrams as part of a sparring move.

### Index

- [ ] Implement playful design of starting screen (waiting for mockup).

### Misc

- [ ] Add realistic content to Terms of Service page
- [ ] Add realistic content to Privacy Policy page
- [ ] Add realistic content to Credits page (partially done)

## Bug Fixes

- [ ] Investigate some benign visitor messages being blocked by moderation
  - Maybe loosening the moderation can fix it.

# Done

[Move here completed To Dos]

- [x] seed profanity_terms.txt (English + German)
- [x] Make Send button more compact (SVG arrow icon, right of input field)
- [x] Bump max sparring exchanges to 10
- [x] Increase number of unique visitor aliases possible
- [x] Debug window on input screen doesn't update after each new message.
- [x] Disable zooming on double-tapping (mobile, excluding Display screen)
- [x] Layout: Respect Safe Area Insets (mobile, excluding Display screen)
- [x] Display exchange pairs in stacked message bubbles
- [x] Display exchange pairs in a masonry layout (shipped as 2 columns, not 3)