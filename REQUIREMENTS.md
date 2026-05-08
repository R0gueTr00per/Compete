# Compete - Project Requirements

## Overview

Compete is a secure web application for managing martial arts competitions (specifically Loong Fu Pai style tournaments). It serves two audiences: competitors who register and view results, and administrators who plan, run, and report on competitions.

## Tech Stack

| Layer | Technology |
|---|---|
| Server-side language | PHP 8.3 |
| Framework | Laravel 11 |
| Database | MySQL 8.0 (Percona) |
| Web server | LiteSpeed (cPanel/Panthur) |
| Hosting | Panthur.com.au shared hosting via cPanel |
| Deployment | cPanel Laravel installer + SSH/Composer |

## Functional Requirements

### Users

A secure web site that has two main functions:
1. Competitors log in to view and enrol into events for a competition; new users can create an account
2. Admin users log in to setup, plan and run competitions

Competitors can authenticate via:
- Email and password (self-registered account)
- Facebook (OAuth)
- Google (OAuth)
- Microsoft (OAuth)

Social login accounts are linked to a competitor profile; on first social login the user completes their profile (dojo, rank, DOB, etc.) before they can enrol.

All data changes are to be logged. Every data screen will have a History button allowing the log to be viewed.

---

### Competitor Profile

Each competitor account stores:
- Surname, First Name, Date of Birth
- Gender (M/F)
- Weight (kg)
- Height (cm) — collected for competitors under 15 years only
- Phone, Email
- Dojo (LFP Dojo name, or "Guest" with style noted)
- Rank: Kyu grade, Dan grade, or experience in years/months

---

### Competition Structure

There are different parts to a competition:

#### a) Creation and Planning
- Define a competition: name, date, start time, competitor check-in time, location and address, enrolment due date
- When creating a new competition, the age bands, rank bands, weight classes, and events are **automatically copied from the most recent previous competition** as a starting point; the admin can modify them after creation
- The competition edit screen shows all events in a tab, allowing the admin to view, reorder (drag-and-drop), add, and remove events without leaving the screen
- Define pricing per competition:
  - First event: $38 (default)
  - Additional events: $12 each (default)
  - Late registration surcharge: $15 if enrolled after the enrolment due date (configurable)
- Define event types available in this competition (e.g. Kata, Tile Breaking, Yakusuko, Semi Contact, Point Sparring, Continuous Sparring, Sumo)
- For each event type, define its divisions (see Division Structure below)
- Set the running order of events and their location (e.g. Mat 3)
- Each competition event has a short code, defaulting to the first letter of the event type name followed by a two-digit counter (e.g. K01 for the first Kata event, K02 for a second); the code is editable by the admin

#### b) Division Structure

Divisions vary by event type. Each division is a combination of relevant filters:

| Event Type | Division filters |
|---|---|
| Kata, Tile Breaking, Point Sparring, Continuous Sparring | Age band + Rank band + Sex |
| Yakusuko | Age band + Rank band + Sex |
| Semi Contact | Age band + Sex (rank not split) |
| Sumo | Weight class + Sex |

**Age bands (examples from current form):** 8 & Under, 9–11, 12–14, 15+, 40+, Under 11, Under 15 — configurable per competition.

**Rank bands (examples):** Open, 10–6 Kyu, 5 Kyu–Black, 5–1 Kyu, Black — configurable per competition.

**Weight classes for Sumo (examples):** Under 30 kg (Flyweight), Under 37 kg (Featherweight), Under 45 kg (Bantamweight), Under 53 kg (Lightweight), Under 60 kg (Welterweight), Under 70 kg (Middleweight), Under 80 kg (Cruiserweight), 80+ kg (Heavyweight).

The admin reserves the right to merge or cancel any division on the day if necessary.

#### c) Managing Enrolments
- Competitors can tick/select multiple events when enrolling; the system calculates the total fee ($38 + $12 × additional events, including each additional division entry within the same event)
- When selecting an event, the competitor must choose their division(s) from a list filtered to divisions that match their age, weight (where applicable), and rank — plus any division marked as Open (no band restrictions); the competitor's own profile data drives the filtering
- A competitor may enrol in **more than one division** for the same event type (e.g. both the 40+ age division and the Open division); each division entry counts as a separate event for fee purposes
- For Yakusuko (partner events), the competitor must nominate their partner; the system validates that the nominated partner has also enrolled and paid — enrolment is not complete until both partners are enrolled
- Add/Remove participants; save and display removed participants with an option to re-add them, recording a reason for removal
- If a participant has not checked in, exclude them from the event but show them in a separate section so they can be re-added if needed
- **Admin can enter an enrolment on behalf of a competitor** (e.g. phone/paper enrolments); the admin selects the competitor and events from the admin panel, and the system applies the same fee calculation and division assignment logic as a self-service enrolment
- **Admin can edit an existing enrolment**: add or remove events the competitor is enrolled in, and change the division assigned to any event; fee is recalculated automatically when events are added or removed
- The admin enrolments list defaults to the competition happening today (or the next upcoming open/running competition); the admin can change the competition filter manually

#### d) Managing Competitor Check-ins on the Day
- The check-in screen defaults to the competition whose date matches today; if none, defaults to the next upcoming open/running competition
- A competitor is checked in **once** for the competition (not separately for each event they entered); check-in is recorded at the enrolment level
- Weight confirmation is recorded once per enrolment check-in (not separately per event); the confirmed weight is automatically applied to every event in that enrolment that requires a weight check, and division re-assignment is triggered for each of those events
- If the confirmed weight at check-in differs from the profile weight and would place the competitor in a different division, a warning is shown and the admin is prompted to update the division (to the correct weight-based division or an open division)
- Checked-in status displays on each event list

#### e) Modifying Defined Events
- Combine, cancel, change mat, add or remove competitors from an event
- When creating or editing a competition, the admin sees all events and their divisions in a single screen without navigating away; events support inline add/edit/delete, drag-to-reorder running order, status filter, location filter, and search by event type name
- Divisions for each event are accessible and editable inline from the competition edit screen (not requiring navigation to a separate competition-event page)
- Events can be assigned to a location (e.g. Mat 1, Mat 2, Mat 3); multiple events can be bulk-assigned to a location at once; the event list can be grouped by location
- Each competition event references a global event type (Kata, Tile Breaking, Yakusuko, etc.); the admin can manage the global list of event types (name, scoring method, division filter, judge count, etc.); event types cannot be deleted if they have been used in any competition
- Global event type settings (scoring method, judge count, target score, division filter) act as **defaults** — each competition event can override these values for that specific competition without affecting other competitions or the global default
- The admin can define the division filter for each event type, selecting from: Age + Rank + Sex, Age + Sex, Weight + Sex, Age + Rank (no sex split), or Age only (no sex or rank split)
- For each competition event, the admin can manage its divisions directly from the competition edit screen

#### f) Entering Scores / Results

Scoring format varies by event type:

| Event Type | Scoring Method |
|---|---|
| Kata | 3 judges each submit a score (baseline ~7.0); competitor's final score is the sum of all three |
| Point Sparring | First to N points (configurable per division, default 5) |
| Continuous Sparring | Win / Loss result only |
| Sumo | First to N points (configurable per division, default 5) |
| Tile Breaking | 3 judges each submit a score; final score is the sum of all three |
| Yakusuko | 3 judges each submit a score; final score is the sum of all three |
| Semi Contact | Win / Loss result only |

- Scores are entered after each event runs
- The app automatically ranks competitors (1st, 2nd, 3rd) based on results; ranking can be manually overridden

#### g) Competitor Portal
- Competitors can log in and see all competitions they are enrolled in
- For each competition, they can see each event they are enrolled in, including their division and the full list of other competitors in that division
- Results are displayed for each event once the event has been run (1st, 2nd, 3rd placements and scores where applicable)
- A full set of results for the competition is visible once the competition is complete

#### h) Admin Dashboard
- The admin home page shows all active competitions (status: open, running) with quick-action buttons to jump directly to that competition's enrolments and check-in screens

#### i) Reporting
- Each competition can produce a PDF document of all results

#### i) Notifications
- Email notifications to competitors (e.g. enrolment confirmation, event reminders)

---

## Non-Functional Requirements
- All data changes must be audited (who changed what and when)
- Authentication must be secure; passwords stored hashed
- After 5 consecutive failed login attempts, an account is locked for 1 hour; the lockout applies account-wide (not per IP) and covers both the competitor portal and admin panel login; the lockout clears automatically after 1 hour or on the next successful login
- The application must be usable on mobile devices (responsive design) for day-of check-in and scoring workflows
- PDF generation must be server-side

## Out of Scope
- Payment processing (costs are defined and calculated but collection is handled externally)
- Live-streaming or bracket visualisation

## Open Questions
- ~~What technology stack is preferred?~~ **Resolved: Laravel 11 / PHP 8.3 / MySQL 8.0 on Panthur cPanel hosting**
- ~~Email notifications?~~ **Resolved: Yes**
- ~~Multiple events per competitor?~~ **Resolved: Yes — $38 first event, $12 each additional**
- ~~Club/team concept?~~ **Resolved: Dojo (competitor's martial arts school)**
- ~~Division structure?~~ **Resolved: varies by event type — age+rank+sex, weight+sex, or age+sex**
- ~~Scoring format?~~ **Resolved: see scoring table in section f)**
- ~~Yakusuko partner validation?~~ **Resolved: both partners must independently enrol and pay; enrolment flagged as incomplete until partner is also enrolled**
- ~~Division templates?~~ **Resolved: when creating a new competition, division definitions default to those of the most recent previous competition and can be modified from there**
