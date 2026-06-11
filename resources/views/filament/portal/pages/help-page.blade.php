<x-filament-panels::page>

    {{-- ── Intro ──────────────────────────────────────────────────────────── --}}
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
        Find answers to common questions about using Kompetic as a competitor. If you can't find what you're looking for,
        use the <a href="{{ \App\Filament\Portal\Pages\SupportPage::getUrl() }}" class="text-primary-600 underline hover:no-underline">Support</a> page to contact us directly.
    </p>

    {{-- ── Section macro ──────────────────────────────────────────────────── --}}
    @php
        $sections = [

            // ── 1. Getting Started ───────────────────────────────────────
            [
                'title' => 'Getting Started',
                'icon'  => 'M12 4.5v15m7.5-7.5h-15',
                'faqs'  => [
                    [
                        'q' => 'How do I create an account?',
                        'a' => 'Visit your organisation\'s Kompetic portal (e.g. <em>yourclub.kompetic.com</em>) and click <strong>Register</strong>. Enter your email address and choose a password. You\'ll receive a verification email — click the link inside to activate your account.',
                    ],
                    [
                        'q' => 'What is a competitor profile?',
                        'a' => 'A competitor profile holds the personal details used to determine which divisions you\'re eligible for — name, date of birth, gender, rank, and dojo. You must complete your profile before you can register for a competition.',
                    ],
                    [
                        'q' => 'How do I complete my profile?',
                        'a' => 'After logging in, go to <strong>Profile</strong> in the sidebar. Fill in your first name, surname, date of birth, gender, rank, and dojo. Save when done. Your profile must be marked complete before registration is available.',
                    ],
                    [
                        'q' => 'Can I manage profiles for my children or family members?',
                        'a' => 'Yes. Go to <strong>Profiles</strong> in the sidebar and click <strong>Add Profile</strong>. You can create additional profiles for dependants (e.g. children) and register them in competitions just like your own profile.',
                    ],
                    [
                        'q' => 'How do I log in?',
                        'a' => 'Visit your organisation\'s portal and click <strong>Log in</strong>. Enter your email and password. If you\'ve forgotten your password, click <strong>Forgot your password?</strong> and follow the reset instructions sent to your email.',
                    ],
                ],
            ],

            // ── 2. Registering for a Competition ─────────────────────────
            [
                'title' => 'Registering for a Competition',
                'icon'  => 'M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.031c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.03a3 3 0 0 1 0-5.199V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z',
                'faqs'  => [
                    [
                        'q' => 'How do I find open competitions?',
                        'a' => 'Your <strong>Dashboard</strong> shows all upcoming competitions that are currently open for registration, as well as competitions that are coming up soon. Click <strong>Register</strong> on any competition card to begin.',
                    ],
                    [
                        'q' => 'How do I register for a competition?',
                        'a' => 'From the Dashboard or the <strong>Register</strong> page in the sidebar, select the competition you want to enter. Choose the competitor profile you\'re registering, then tick the events and divisions you wish to compete in. Add to cart when ready.',
                    ],
                    [
                        'q' => 'What are events and divisions?',
                        'a' => 'A <strong>competition event</strong> is a category of competition (e.g. Kata, Kumite, Team Kata). A <strong>division</strong> is a bracket within that event, grouped by age, rank, weight, and/or gender. The system automatically shows only the divisions you are eligible for based on your profile.',
                    ],
                    [
                        'q' => 'Can I register for multiple events?',
                        'a' => 'Yes. You can register for as many events as are available for your profile in a single competition. The first event is charged at the standard rate and additional events are charged at the additional-event rate (as set by the organiser).',
                    ],
                    [
                        'q' => 'What is a partner event?',
                        'a' => 'Some events (e.g. Kata Pairs or Team Kata) require a partner. When registering for one of these events, you will be prompted to nominate your partner\'s name so the organiser can pair you correctly.',
                    ],
                    [
                        'q' => 'What does "Late registration" mean?',
                        'a' => 'If the normal registration deadline has passed but the competition is still accepting entries, your registration will be marked as <strong>late</strong> and a late surcharge will be added to your fees. This is set by the organiser.',
                    ],
                    [
                        'q' => 'How do I submit my registration?',
                        'a' => 'Once you\'ve added all your events to the cart, go to the <strong>Cart</strong> page (shopping cart icon in the navigation). Review your items and fees, then click <strong>Submit Registration</strong>. You will receive a confirmation and invoice by email.',
                    ],
                    [
                        'q' => 'When do I pay?',
                        'a' => 'Kompetic generates an invoice when you submit your cart. Payment is made directly to the organiser — not through Kompetic — using the details provided in your invoice email. Some organisations collect payment on competition day at check-in.',
                    ],
                ],
            ],

            // ── 3. Managing Your Registrations ───────────────────────────
            [
                'title' => 'Managing Your Registrations',
                'icon'  => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
                'faqs'  => [
                    [
                        'q' => 'Where can I see my registrations?',
                        'a' => 'Go to <strong>Transactions</strong> in the sidebar. This page lists all your submitted registrations, grouped by cart/transaction. Each entry shows which events you\'re registered for and your current registration status.',
                    ],
                    [
                        'q' => 'Can I change which events I\'m entered in after submitting?',
                        'a' => 'Yes, while the competition is still <strong>open</strong>. On the Transactions page, click <strong>Edit Events</strong> next to the relevant registration. You can add or remove events within the same competition. Changes take effect immediately.',
                    ],
                    [
                        'q' => 'How do I withdraw from a competition?',
                        'a' => 'On the <strong>Transactions</strong> page, click <strong>Withdraw</strong> next to the registration you want to cancel. You\'ll be asked to provide a reason. Withdrawals are only available while the competition is open and before the organiser\'s withdrawal deadline.',
                    ],
                    [
                        'q' => 'What happens to my fees if I withdraw?',
                        'a' => 'Refund eligibility is determined by the organiser\'s cancellation policy. Contact the organiser directly (via the <strong>Support</strong> page) to discuss refunds after withdrawing.',
                    ],
                    [
                        'q' => 'I can\'t see a Withdraw button — why?',
                        'a' => 'Withdrawal is not available if: you have already been checked in, the competition is no longer open, or the organiser\'s withdrawal cutoff date has passed. If none of these apply, contact the organiser for help.',
                    ],
                ],
            ],

            // ── 4. Competition Day ────────────────────────────────────────
            [
                'title' => 'Competition Day',
                'icon'  => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
                'faqs'  => [
                    [
                        'q' => 'What is my check-in code?',
                        'a' => 'Your check-in code is a unique 8-character code assigned to your registration. Find it on your <strong>Dashboard</strong> under the competition card, or in your confirmation email. Present this code to the check-in desk on competition day.',
                    ],
                    [
                        'q' => 'What happens at check-in?',
                        'a' => 'A staff member will enter or scan your check-in code. If you\'re competing in a weight-division event, your weight will be recorded and confirmed. Once checked in, you\'ll be able to compete. Make sure to arrive before the check-in deadline.',
                    ],
                    [
                        'q' => 'What if I arrive late?',
                        'a' => 'If the competition has already started, you can still be checked in but some events you were entered in may have already begun. Speak to the check-in desk as soon as you arrive.',
                    ],
                    [
                        'q' => 'Where can I see the schedule on the day?',
                        'a' => 'Go to <strong>Schedule</strong> in the sidebar to see the full running order, including which mat/location each division is assigned to and the planned start times. The schedule is also publicly available — you can share the link with family and spectators.',
                    ],
                ],
            ],

            // ── 5. Results ───────────────────────────────────────────────
            [
                'title' => 'Results',
                'icon'  => 'M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0',
                'faqs'  => [
                    [
                        'q' => 'How do I view my results?',
                        'a' => 'Your <strong>Dashboard</strong> shows results for all competitions you\'ve participated in. Click on a competition card to see your placement in each event.',
                    ],
                    [
                        'q' => 'What is the AI performance summary?',
                        'a' => 'After a competition, Kompetic can generate a short personalised summary of your performance using AI. If this feature is enabled by your organiser, it will appear on your Dashboard alongside your results a short time after the competition concludes.',
                    ],
                    [
                        'q' => 'Can I see full results for a competition?',
                        'a' => 'Yes. Go to the <strong>Results</strong> section on your Dashboard or navigate to the competition and choose <strong>View Full Results</strong> to see all divisions and placements.',
                    ],
                    [
                        'q' => 'What do the result statuses mean?',
                        'a' => '<ul class="list-disc list-inside space-y-1"><li><strong>1st / 2nd / 3rd / 4th</strong> — Final placement</li><li><strong>DQ</strong> — Disqualified</li><li><strong>Forfeit</strong> — Competitor forfeited their match or division</li></ul>',
                    ],
                ],
            ],

            // ── 6. Account & Preferences ─────────────────────────────────
            [
                'title' => 'Account & Preferences',
                'icon'  => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
                'faqs'  => [
                    [
                        'q' => 'How do I change my password?',
                        'a' => 'Go to <strong>Preferences</strong> in the sidebar under Account. Enter your current password and your new password, then save.',
                    ],
                    [
                        'q' => 'How do I enable two-factor authentication?',
                        'a' => 'In the sidebar, go to <strong>Preferences</strong> and look for the <strong>Two-Factor Authentication</strong> section. Follow the on-screen instructions to set up an authenticator app.',
                    ],
                    [
                        'q' => 'How do I manage my dojos?',
                        'a' => 'Go to <strong>Dojos</strong> in the sidebar. You can add, edit, or remove dojos associated with your account. Your dojo is used for medal-tally reporting and may be required during registration.',
                    ],
                    [
                        'q' => 'I need more help — how do I contact support?',
                        'a' => 'Go to <strong>Support</strong> in the sidebar. You can send a message to either Kompetic Platform Support or your competition organiser, depending on the nature of your question.',
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
            <p class="text-sm text-primary-700 dark:text-primary-300 mt-0.5">Our support team is here for you. Send us a message and we'll get back to you as soon as possible.</p>
        </div>
        <a
            href="{{ \App\Filament\Portal\Pages\SupportPage::getUrl() }}"
            class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
        >
            Contact Support
        </a>
    </div>

</x-filament-panels::page>
