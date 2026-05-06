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
- Define pricing per competition:
  - First event: $38 (default)
  - Additional events: $12 each (default)
  - Late registration surcharge: $15 if enrolled after the enrolment due date (configurable)
- Define event types available in this competition (e.g. Kata, Tile Breaking, Yakusuko, Semi Contact, Point Sparring, Continuous Sparring, Sumo)
- For each event type, define its divisions (see Division Structure below)
- Set the running order of events and their location (e.g. Mat 3)

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
- Competitors can tick/select multiple events when enrolling; the system calculates the total fee ($38 + $12 × additional events)
- For Yakusuko (partner events), the competitor must nominate their partner; the system validates that the nominated partner has also enrolled and paid — enrolment is not complete until both partners are enrolled
- Add/Remove participants; save and display removed participants with an option to re-add them, recording a reason for removal
- If a participant has not checked in, exclude them from the event but show them in a separate section so they can be re-added if needed

#### d) Managing Competitor Check-ins on the Day
- Mark participants as checked-in; this status displays on each event list
- Weight confirmation on check-in for Sumo and Semi Contact events

#### e) Modifying Defined Events
- Combine, cancel, change mat, add or remove competitors from an event

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

#### h) Reporting
- Each competition can produce a PDF document of all results

#### i) Notifications
- Email notifications to competitors (e.g. enrolment confirmation, event reminders)

---

## Non-Functional Requirements
- All data changes must be audited (who changed what and when)
- Authentication must be secure; passwords stored hashed
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
