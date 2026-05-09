<x-filament-panels::page>
    {{-- Competition selector --}}
    <div class="mb-5 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-800 dark:bg-primary-950/30">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-400">Competition</p>
        <x-filament::input.wrapper>
            <select wire:model.live="competition_id"
                class="w-full block border-0 bg-transparent py-1.5 text-sm text-gray-900 dark:text-white focus:ring-0">
                <option value="">— Select competition —</option>
                @foreach ($this->getCompetitions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </x-filament::input.wrapper>
    </div>

    @if (! $this->competition_id)
        <p class="text-center text-gray-400 py-12">Select a competition to view results.</p>
    @else
        @php $events = $this->getResultsData(); @endphp

        @if ($events->isEmpty())
            <p class="text-center text-gray-400 py-12">No scored events yet for this competition.</p>
        @else
            <div class="space-y-6">
                @foreach ($events as $compEvent)
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                            {{ $compEvent->event_code }} — {{ $compEvent->name }}
                            @if ($compEvent->location_label)
                                <span class="font-normal normal-case">({{ $compEvent->location_label }})</span>
                            @endif
                        </h2>

                        <div class="space-y-4">
                            @foreach ($compEvent->divisions as $division)
                                @php
                                    $entries = $division->enrolmentEvents
                                        ->sortBy(fn ($ee) => $ee->result?->placement ?? 999);
                                    if ($entries->isEmpty()) continue;
                                @endphp

                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                    <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ $division->full_label }}
                                        </span>
                                        @if ($division->status === 'complete')
                                            <span class="ml-2 inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-900/30 dark:text-success-400">Complete</span>
                                        @else
                                            <span class="ml-2 inline-flex items-center rounded-full bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-900/30 dark:text-warning-400">In progress</span>
                                        @endif
                                    </div>

                                    <table class="w-full text-sm">
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($entries as $ee)
                                                @php
                                                    $result  = $ee->result;
                                                    $profile = $ee->enrolment->competitor?->competitorProfile;
                                                    $name    = $profile
                                                        ? "{$profile->first_name} {$profile->surname}"
                                                        : ($ee->enrolment->competitor?->name ?? '—');
                                                    $dojo = $ee->enrolment->dojo_type === 'guest'
                                                        ? ($ee->enrolment->guest_style ?? 'Guest')
                                                        : ($ee->enrolment->dojo_name ?? '—');
                                                @endphp
                                                <tr class="{{ $result?->disqualified ? 'opacity-50' : '' }}">
                                                    <td class="pl-4 py-2.5 w-12 font-bold text-center">
                                                        @if ($result?->placement)
                                                            @switch($result->placement)
                                                                @case(1) 🥇 @break
                                                                @case(2) 🥈 @break
                                                                @case(3) 🥉 @break
                                                                @default <span class="text-gray-500">{{ $result->placement }}</span>
                                                            @endswitch
                                                        @else
                                                            <span class="text-gray-300">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="py-2.5 pr-4">
                                                        <p class="font-medium text-gray-900 dark:text-white">
                                                            {{ $name }}
                                                            @if ($result?->disqualified)
                                                                <span class="text-xs text-danger-600 ml-1">DQ</span>
                                                            @endif
                                                            @if ($result?->placement_overridden)
                                                                <span class="text-xs text-warning-600 ml-1">*</span>
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-400">{{ $dojo }}</p>
                                                    </td>
                                                    <td class="py-2.5 pr-4 text-right text-gray-600 dark:text-gray-400">
                                                        @if ($result)
                                                            @if (in_array($compEvent->effectiveScoringMethod(), ['judges_total', 'judges_average']) && $result->total_score !== null)
                                                                {{ number_format((float) $result->total_score, 2) }}
                                                            @elseif ($compEvent->effectiveScoringMethod() === 'first_to_n' && $result->total_score !== null)
                                                                {{ (int) $result->total_score }} pts
                                                            @elseif ($result->win_loss)
                                                                {{ ucfirst($result->win_loss) }}
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-filament-panels::page>
