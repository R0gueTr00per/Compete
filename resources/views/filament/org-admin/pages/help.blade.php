<x-filament-panels::page>

    {{-- ── Intro ──────────────────────────────────────────────────────────── --}}
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Find answers to common questions about running competitions on Kompetic. If you need further assistance,
        use the <a href="{{ \App\Filament\OrgAdmin\Pages\Support::getUrl() }}" class="text-primary-600 underline hover:no-underline">Support</a> page to contact our team.
    </p>

    @php
        $sections = [

            // ── 1. Getting Started ───────────────────────────────────────
            [
                'title' => 'Getting Started',
                'icon'  => 'M12 4.5v15m7.5-7.5h-15',
                'faqs'  => [
                    [
                        'q' => 'What is Kompetic?',
                        'a' => 'Kompetic is a competition management platform for martial arts and sporting organisations. It handles competitor registration (enrolment), scheduling, scoring, and results — all in one place.',
                    ],
                    [
                        'q' => 'How is the admin panel structured?',
                        'a' => 'The sidebar is organised into sections: <strong>Competitions</strong> (manage your events), <strong>Members & Dojos</strong> (manage people), <strong>Operations</strong> (check-in, scoring, results on competition day), and <strong>Account</strong> (settings, billing, support). The top of the sidebar also shows a quick-action Dashboard.',
                    ],
                    [
                        'q' => 'How do I invite another admin?',
                        'a' => 'Go to <strong>Members</strong> in the sidebar. Find the member you want to promote and edit their record — you can assign the <em>Org Admin</em> role to grant full admin access, or an <em>Official</em> role for limited day-of access. Members can also be invited by email via an invitation link.',
                    ],
                    [
                        'q' => 'How do I update my organisation\'s details?',
                        'a' => 'Go to <strong>Organisation Settings</strong> under the Account section in the sidebar. Here you can update your organisation name, contact email, platform fee rate, cancellation policy, custom registration fields, and AI feature toggles.',
                    ],
                ],
            ],

            // ── 2. Setting Up a Competition ──────────────────────────────
            [
                'title' => 'Setting Up a Competition',
                'icon'  => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
                'faqs'  => [
                    [
                        'q' => 'How do I create a new competition?',
                        'a' => 'Go to <strong>Competitions</strong> in the sidebar and click <strong>New Competition</strong>. Fill in the name, date, location, check-in time, and enrolment deadline. You can also set fees: first-event fee, additional-event fee, and late surcharge.',
                    ],
                    [
                        'q' => 'What are competition events?',
                        'a' => 'A <strong>competition event</strong> is a discipline within your competition (e.g. Individual Kata, Kumite, Team Kata). Inside each competition, click <strong>Events</strong> to add, edit, or reorder your events.',
                    ],
                    [
                        'q' => 'What are divisions?',
                        'a' => 'Divisions are the individual brackets within an event, grouped by age band, rank band, weight class, and/or gender. When you configure age bands, rank bands, and weight classes for your competition, Kompetic generates the divisions automatically when competitors enrol.',
                    ],
                    [
                        'q' => 'What scoring methods are available?',
                        'a' => '<ul class="list-disc list-inside space-y-1"><li><strong>Judges Total</strong> — Multiple judges each give a score; scores are summed (optionally with high/low drop).</li><li><strong>Win / Loss</strong> — Head-to-head: competitors win, lose, or draw each match.</li><li><strong>First to N</strong> — First competitor to reach a target score wins.</li><li><strong>Timed Points</strong> — Points scored within a time limit.</li></ul>',
                    ],
                    [
                        'q' => 'What tournament formats are available?',
                        'a' => '<ul class="list-disc list-inside space-y-1"><li><strong>Once Off</strong> — Each competitor performs/fights once; ranked by score.</li><li><strong>Round Robin</strong> — Everyone competes against everyone in their division.</li><li><strong>Single Elimination</strong> — Knock-out bracket.</li><li><strong>Double Elimination</strong> — Two losses required to be eliminated.</li><li><strong>Repechage</strong> — Eliminated competitors can return via a second-chance bracket.</li><li><strong>SE + 3rd Place</strong> — Single elimination with a bronze-medal playoff.</li></ul>',
                    ],
                    [
                        'q' => 'Can I copy a previous competition?',
                        'a' => 'Yes. From the <strong>Competitions</strong> list, use the duplicate/copy action on an existing competition. All events and configuration are copied. Registrations are not copied.',
                    ],
                    [
                        'q' => 'What are competition templates?',
                        'a' => 'Templates are reusable competition configurations. Go to <strong>Templates</strong> in the sidebar to manage them. When creating a new competition you can start from an active template to pre-fill events and settings.',
                    ],
                    [
                        'q' => 'How do I open registrations?',
                        'a' => 'Inside the competition, find the <strong>Status</strong> control and change it to <strong>Open</strong>. The competition will immediately appear on the competitor portal and accept registrations.',
                    ],
                    [
                        'q' => 'What are the competition statuses?',
                        'a' => '<ul class="list-disc list-inside space-y-1"><li><strong>Planning</strong> — Not yet visible to competitors.</li><li><strong>Open</strong> — Visible; registrations accepted.</li><li><strong>Registrations Closed</strong> — Visible but no new registrations.</li><li><strong>Check-in</strong> — Check-in desk mode active.</li><li><strong>Running</strong> — Competition is in progress; scoring active.</li><li><strong>Complete</strong> — Competition finished; results published.</li></ul>',
                    ],
                ],
            ],

            // ── 3. Managing Registrations ────────────────────────────────
            [
                'title' => 'Managing Registrations',
                'icon'  => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.031c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.03a3 3 0 0 1 0-5.199V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z',
                'faqs'  => [
                    [
                        'q' => 'How do I view all registrations for a competition?',
                        'a' => 'Go to <strong>Registrations</strong> in the sidebar. You can filter by competition to see all registered competitors, their status, payment status, and which events they\'re entered in.',
                    ],
                    [
                        'q' => 'How do I manually add a registration?',
                        'a' => 'In the <strong>Registrations</strong> section, click <strong>Add Registration</strong> (or use the admin registration wizard). Search for the competitor by name, select the competition and events, and submit. This is useful for walk-ins or late entries.',
                    ],
                    [
                        'q' => 'How do I handle late registrations?',
                        'a' => 'If the registration deadline has passed but you want to accept a new entry, use the manual registration process. Mark it as late if appropriate so the late surcharge is applied. Alternatively, extend the registration deadline in the competition settings.',
                    ],
                    [
                        'q' => 'How do I manage refund requests?',
                        'a' => 'Withdrawn registrations that have requested a refund appear in <strong>Refund Requests</strong> under the Account section. Process refunds directly with the competitor and mark them as resolved in Kompetic.',
                    ],
                    [
                        'q' => 'How do I view payment status?',
                        'a' => 'The <strong>Transactions</strong> page shows all submitted carts with payment status. You can also mark payments as received from the Check-in page on competition day.',
                    ],
                ],
            ],

            // ── 4. Check-in ──────────────────────────────────────────────
            [
                'title' => 'Check-in',
                'icon'  => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
                'faqs'  => [
                    [
                        'q' => 'How does check-in work?',
                        'a' => 'Go to the <strong>Check-in</strong> page in the sidebar. Select the competition (it auto-selects today\'s event). Enter or scan a competitor\'s 8-character check-in code, or search by name. Once found, click <strong>Check In</strong>.',
                    ],
                    [
                        'q' => 'What is the check-in code?',
                        'a' => 'Each registration has a unique 8-character alphanumeric code. Competitors find this code in their confirmation email or on their portal Dashboard. You can also search by name if they don\'t have the code.',
                    ],
                    [
                        'q' => 'How do I handle weight verification?',
                        'a' => 'If a competitor is entered in an event that requires a weight check, you\'ll see a weight input field at check-in. Enter the competitor\'s weighed-in weight and click <strong>Confirm Weight</strong>. Kompetic will check whether they still fit their registered division. If they\'ve moved weight class, you\'ll be prompted to accept the division change or keep them in their original division.',
                    ],
                    [
                        'q' => 'How do I record payment at check-in?',
                        'a' => 'If a competitor\'s payment is still outstanding, a <strong>Record Payment</strong> button appears on their check-in card. Click it to mark their fees as received on the spot.',
                    ],
                    [
                        'q' => 'Can I undo a check-in?',
                        'a' => 'Yes — as long as scores have not yet been entered for that competitor, an <strong>Undo Check-in</strong> option is available. If the competition is already running, take care to confirm they haven\'t been called yet before reversing the check-in.',
                    ],
                ],
            ],

            // ── 5. Scheduling ─────────────────────────────────────────────
            [
                'title' => 'Scheduling',
                'icon'  => 'M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 7.746 6 7.125v-1.5M4.875 8.25C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.125v-1.5m1.125 2.625c-.621 0-1.125.504-1.125 1.125v1.5m2.625-2.625c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-12 5.25v-5.25m0 5.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125m-12 0v-1.5c0-.621.504-1.125 1.125-1.125M4.875 14.625v-1.5c0-.621-.504-1.125-1.125-1.125m0 0H2.25m12.75 6H4.875',
                'faqs'  => [
                    [
                        'q' => 'How do I assign divisions to locations (mats)?',
                        'a' => 'Go to <strong>Schedule View</strong> in the sidebar. Divisions that have not been assigned to a location are shown in the unassigned list. Drag and drop them onto a mat column, or use the edit action to set a location label and planned start time.',
                    ],
                    [
                        'q' => 'How do I make the schedule visible to competitors?',
                        'a' => 'The public schedule becomes available automatically once the competition status moves to <strong>Check-in</strong> or <strong>Running</strong>. Competitors and spectators can view it without logging in at the public schedule URL (shown on the Schedule View page).',
                    ],
                    [
                        'q' => 'Can I add breaks to the schedule?',
                        'a' => 'Yes. In the Schedule View, use the <strong>Add Break</strong> action to insert a named break (e.g. Lunch Break) with a start time. Breaks appear on the public schedule.',
                    ],
                ],
            ],

            // ── 6. Scoring ────────────────────────────────────────────────
            [
                'title' => 'Scoring',
                'icon'  => 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0',
                'faqs'  => [
                    [
                        'q' => 'How do I enter scores?',
                        'a' => 'Go to <strong>Scoring</strong> in the sidebar. Select the competition, then click into the division you want to score. The scoring screen adapts to the event type — enter judge scores for panel events, or record match results for bracket events.',
                    ],
                    [
                        'q' => 'What does "scoring locked" mean?',
                        'a' => 'When you open a division for scoring, it becomes locked to your session so two officials can\'t score the same division simultaneously. If a division is locked by another user, their name is shown. Locks are released automatically when you navigate away or close the page.',
                    ],
                    [
                        'q' => 'How do I record a disqualification or forfeit?',
                        'a' => 'On the scoring screen, find the competitor\'s row and use the <strong>DQ</strong> or <strong>Forfeit</strong> action. DQ and forfeit competitors are placed last in the division standings.',
                    ],
                    [
                        'q' => 'How do I handle penalties?',
                        'a' => 'For events with penalties enabled (e.g. Kumite), a penalty button is available next to each competitor or match in the scoring interface. Each penalty type (Warning, Penalty Point, Hansoku) is configured per event and can optionally trigger an automatic disqualification after a set number.',
                    ],
                    [
                        'q' => 'What happens when I complete a division?',
                        'a' => 'Click <strong>Complete Division</strong> when all scores have been entered. The division status changes to <em>complete</em>, placements are finalised, and results become visible to competitors on their portal Dashboard.',
                    ],
                    [
                        'q' => 'Can I override a placement?',
                        'a' => 'Yes. In the Results page, you can enable <strong>Placement Override Mode</strong> for a division and manually reorder placements if needed (e.g. after a protest or a technical correction).',
                    ],
                    [
                        'q' => 'How do I combine under-subscribed divisions?',
                        'a' => 'If a division has too few competitors to run on its own, you can merge it into another division from the division management view inside the competition. Combined competitors compete together; their original division is recorded for medal tally purposes.',
                    ],
                ],
            ],

            // ── 7. Results & Reporting ────────────────────────────────────
            [
                'title' => 'Results & Reporting',
                'icon'  => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z',
                'faqs'  => [
                    [
                        'q' => 'How do I view results for a competition?',
                        'a' => 'Go to <strong>Results</strong> in the sidebar. Select the competition to see a full breakdown of all divisions, placements, scores, and any DQs or forfeits.',
                    ],
                    [
                        'q' => 'Can I export results as a PDF?',
                        'a' => 'Yes. From the Results page, use the <strong>Export PDF</strong> action. You can generate a full results sheet, a medal tally by competitor, or a medal tally by dojo.',
                    ],
                    [
                        'q' => 'What are AI Competition Insights?',
                        'a' => 'After completing a competition, Kompetic can generate an AI-powered summary of the event — division completion times, competitor participation patterns, and highlights. This feature must be enabled in Organisation Settings and requires a configured AI API key.',
                    ],
                ],
            ],

            // ── 8. Members, Dojos & Officials ────────────────────────────
            [
                'title' => 'Members, Dojos & Officials',
                'icon'  => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
                'faqs'  => [
                    [
                        'q' => 'What is the difference between Members and Competitors?',
                        'a' => '<strong>Members</strong> are the user accounts (login credentials). <strong>Competitors</strong> are the profiles associated with those accounts. One member account can own multiple competitor profiles (e.g. a parent with two children competing).',
                    ],
                    [
                        'q' => 'How do I manage dojos?',
                        'a' => 'Go to <strong>Dojos</strong> in the sidebar. You can add, edit, or remove dojos. Competitors associate themselves with a dojo on their profile, which is used for the medal tally by dojo report.',
                    ],
                    [
                        'q' => 'What are Official Roles?',
                        'a' => 'Official Roles let you grant competition-day access to helpers (e.g. a scoring table operator or a check-in volunteer) without giving them full admin access. Go to <strong>Official Roles</strong> to create roles with specific permissions (scoring, check-in, results) and then assign officials to those roles for a specific competition.',
                    ],
                    [
                        'q' => 'How do I add a competition official?',
                        'a' => 'Inside a competition, go to the <strong>Officials</strong> tab. Add a member and assign them an Official Role. They will then have access to the permitted pages (e.g. Check-in or Scoring) on competition day.',
                    ],
                ],
            ],

        ];
    @endphp

    {{-- ── Rendered sections ──────────────────────────────────────────────── --}}
    <div class="space-y-4">
        @foreach ($sections as $section)
            <div
                x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }"
                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
            >
                {{-- Section header --}}
                <button
                    type="button"
                    x-on:click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    <div class="flex items-center gap-3">
                        <span class="shrink-0 w-5 h-5 text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $section['icon'] }}" />
                            </svg>
                        </span>
                        <span class="text-base font-semibold text-gray-900 dark:text-white">{{ $section['title'] }}</span>
                    </div>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        class="w-5 h-5 text-gray-400 transition-transform duration-200"
                        x-bind:class="open ? 'rotate-180' : ''"
                    >
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </button>

                {{-- FAQs --}}
                <div x-show="open" class="border-t border-gray-100 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($section['faqs'] as $faq)
                        <div x-data="{ open: false }" class="px-5">
                            <button
                                type="button"
                                x-on:click="open = !open"
                                class="w-full flex items-center justify-between py-4 text-left focus:outline-none"
                            >
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200 pr-4">{{ $faq['q'] }}</span>
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="shrink-0 w-4 h-4 text-gray-400 transition-transform duration-200"
                                    x-bind:class="open ? 'rotate-45' : ''"
                                >
                                    <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                                </svg>
                            </button>
                            <div x-show="open">
                                <div class="pb-4 text-sm text-gray-600 dark:text-gray-400 leading-relaxed prose prose-sm dark:prose-invert max-w-none">
                                    {!! $faq['a'] !!}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Footer CTA ──────────────────────────────────────────────────────── --}}
    <div class="mt-8 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/30 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-primary-900 dark:text-primary-100">Still need help?</p>
            <p class="text-sm text-primary-700 dark:text-primary-300 mt-0.5">Contact the Kompetic support team for platform, billing, or technical assistance.</p>
        </div>
        <a
            href="{{ \App\Filament\OrgAdmin\Pages\Support::getUrl() }}"
            class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
        >
            Contact Support
        </a>
    </div>

</x-filament-panels::page>
