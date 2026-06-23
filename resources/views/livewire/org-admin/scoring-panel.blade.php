<div x-data="{ cancelling: false }" x-on:scoring-cancel-confirmed.window="cancelling = true">
    <style>
        input[type=number]::-webkit-outer-spin-button,
        input[type=number]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>

    @if ($div)
        @php
            $isReadOnly = $div->status === 'complete';
            $isBracket  = $this->isTournament();
        @endphp

        <div x-show="!cancelling" class="mb-2 rounded-lg border border-primary-200 dark:border-primary-700 bg-white dark:bg-gray-800 p-4 scoring-panel-glow">

            {{-- Panel header --}}
            @if (! $isReadOnly)
                <div class="flex items-center justify-between mb-4">
                    @if ($this->rollcallRequired)
                        <div class="flex items-center gap-2 text-xs font-medium">
                            <span class="flex items-center gap-1.5 {{ $this->rollcallMode ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400 dark:text-gray-600' }}">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold
                                    {{ $this->rollcallMode ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">1</span>
                                Rollcall
                            </span>
                            <x-heroicon-m-arrow-right class="h-3 w-3 text-gray-300 dark:text-gray-600" />
                            <span class="flex items-center gap-1.5 {{ ! $this->rollcallMode ? 'text-primary-700 dark:text-primary-300' : 'text-gray-400 dark:text-gray-600' }}">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-bold
                                    {{ ! $this->rollcallMode ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-500' }}">2</span>
                                Scoring
                            </span>
                        </div>
                    @else
                        <div></div>
                    @endif
                    <div></div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5 mb-3">
                    @if (! $this->rollcallMode)
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-trophy class="w-3 h-3 shrink-0" />
                            {{ $this->getAwardedPlacesLabel() }}
                        </span>
                    @endif
                    @foreach ($this->getScoringSettingPills() as $pill)
                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $pill }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Sub-components --}}
            @if ($this->rollcallMode)
                <livewire:org-admin.rollcall-panel
                    :division-id="$division_id"
                    :competition-id="$competition_id"
                    wire:key="rollcall-{{ $division_id }}" />
            @else
                @if ($isBracket)
                    <livewire:org-admin.bracket-scoring-panel
                        :division-id="$division_id"
                        :competition-id="$competition_id"
                        wire:key="bracket-{{ $division_id }}" />
                @else
                    <livewire:org-admin.judge-score-panel
                        :division-id="$division_id"
                        wire:key="judge-{{ $division_id }}" />
                    <livewire:org-admin.tiebreaker-panel
                        :division-id="$division_id"
                        wire:key="tiebreaker-{{ $division_id }}" />
                @endif
            @endif

            {{-- Panel footer --}}
            @if ($div->status !== 'complete')
                <div class="mt-4 gap-3 {{ $this->rollcallMode ? 'flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between' : 'flex items-center justify-between' }}">
                    <div>
                        @if ($this->rollcallMode)
                            <x-filament::button color="gray" size="sm"
                                wire:click="cancelScoring">
                                Cancel
                            </x-filament::button>
                        @else
                            <x-filament::button color="gray" size="sm"
                                x-on:click="$dispatch('open-modal', { id: 'confirm-cancel-scoring' })">
                                Cancel
                            </x-filament::button>
                        @endif
                    </div>
                    <div class="{{ $this->rollcallMode ? 'flex items-stretch sm:items-center gap-2' : 'flex items-center gap-2' }}">
                        @if ($this->rollcallMode)
                            <x-filament::button color="primary"
                                class="w-full justify-center sm:w-auto"
                                x-on:click="$dispatch('begin-scoring-pressed')"
                                icon="heroicon-m-arrow-right" icon-position="after">
                                Begin Scoring
                            </x-filament::button>
                        @endif
                        @if (! $this->rollcallMode && $this->rollcallRequired)
                            <x-filament::button color="gray" size="sm"
                                x-on:click="$dispatch('open-modal', { id: 'confirm-rollcall-return' })"
                                icon="heroicon-m-arrow-left">
                                Back to rollcall
                            </x-filament::button>
                        @endif
                        @if ($this->isScoringComplete())
                            <x-filament::button color="success" size="sm"
                                x-on:click="$dispatch('open-modal', { id: 'confirm-mark-complete' })">
                                Mark complete
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-4 flex flex-col gap-2">
                    @if ($div->completed_at)
                        @php
                            $completedUser    = $div->completedBy;
                            $completedName    = $completedUser?->selfProfile?->full_name;
                            $completedDisplay = $completedName
                                ? "{$completedName} ({$completedUser->email})"
                                : ($completedUser?->email ?? 'Unknown');
                        @endphp
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                            Completed by {{ $completedDisplay }}
                            on {{ $div->completed_at->format('d M Y \a\t g:i A') }}
                        </p>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <x-filament::button color="gray" size="sm" wire:click="closeSelf">
                            Close
                        </x-filament::button>
                        <x-filament::button color="warning" size="sm"
                            x-on:click="$dispatch('open-modal', { id: 'confirm-reactivate-division' })">
                            Re-activate scoring
                        </x-filament::button>
                    </div>
                </div>
            @endif

        </div>
    @endif

    <div class="h-0 overflow-hidden">
        <x-filament::modal id="confirm-cancel-scoring" width="sm">
            <x-slot name="heading">Cancel scoring?</x-slot>
            <x-slot name="description">All scores and placements will be cleared.</x-slot>
            <x-slot name="footerActions">
                <x-filament::button color="danger"
                    wire:click="cancelScoring"
                    x-on:click="window.dispatchEvent(new CustomEvent('scoring-cancel-confirmed')); $dispatch('close-modal', { id: 'confirm-cancel-scoring' })">
                    Yes, cancel
                </x-filament::button>
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-cancel-scoring' })">
                    Keep scoring
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        <x-filament::modal id="confirm-rollcall-return" width="sm">
            <x-slot name="heading">Return to rollcall?</x-slot>
            <x-slot name="description">All scores, placements, and the bracket will be cleared.</x-slot>
            <x-slot name="footerActions">
                <x-filament::button color="danger"
                    wire:click="returnToRollcall"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-rollcall-return' })">
                    Yes, return to rollcall
                </x-filament::button>
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-rollcall-return' })">
                    Keep scoring
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        <x-filament::modal id="confirm-reactivate-division" width="sm">
            <x-slot name="heading">Re-activate scoring?</x-slot>
            <x-slot name="description">This will reopen the division for editing. You will need to mark it complete again when finished.</x-slot>
            <x-slot name="footerActions">
                <x-filament::button color="warning"
                    wire:click="reactivateDivision"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-reactivate-division' })">
                    Yes, re-activate
                </x-filament::button>
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-reactivate-division' })">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        <x-filament::modal id="confirm-mark-complete" width="sm">
            <x-slot name="heading">Mark division complete?</x-slot>
            <x-slot name="description">Results will be finalised and visible to competitors.</x-slot>
            <x-slot name="footerActions">
                <x-filament::button color="success"
                    wire:click="markDivisionComplete"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-mark-complete' })">
                    Yes, mark complete
                </x-filament::button>
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'confirm-mark-complete' })">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        {{-- Penalty reason selection modal --}}
        <x-filament::modal id="penalty-reason-modal" width="sm" :close-by-clicking-away="false">
            <x-slot name="heading">
                Select reason
                @if ($penaltyModalType)
                    <span class="ml-1 text-sm font-normal text-gray-500 dark:text-gray-400">— {{ $this->getPenaltyLabel($penaltyModalType) }}</span>
                @endif
            </x-slot>
            <div class="space-y-2">
                @foreach ($penaltyModalReasons as $reason)
                    <button type="button"
                        wire:click="confirmPenalty(@js($reason))"
                        x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })"
                        class="w-full text-left px-3 py-2 rounded-lg border text-sm transition-colors border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-primary-400 hover:bg-primary-50 dark:hover:border-primary-600 dark:hover:bg-primary-900/20 active:scale-95">
                        {{ $reason }}
                    </button>
                @endforeach
                @if (in_array($penaltyModalType, ['dq', 'forfeit']))
                    <div class="{{ $penaltyModalReasons ? 'border-t border-gray-100 dark:border-gray-700 pt-3 mt-1' : '' }}">
                        <input type="text"
                            wire:model="penaltyModalFreeText"
                            placeholder="Enter reason (optional)..."
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                @endif
            </div>
            <x-slot name="footerActions">
                @if (in_array($penaltyModalType, ['dq', 'forfeit']))
                    <x-filament::button
                        wire:click="confirmPenalty"
                        x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })">
                        Apply
                    </x-filament::button>
                @endif
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'penalty-reason-modal' })">
                    Cancel
                </x-filament::button>
            </x-slot>
        </x-filament::modal>

        {{-- Note modal --}}
        <div
            x-data="{ noteResultId: null, noteText: '' }"
            x-on:open-note-modal.window="noteResultId = $event.detail.resultId; noteText = $event.detail.note; $dispatch('open-modal', { id: 'note-modal' })"
        >
            <x-filament::modal id="note-modal" width="md">
                <x-slot name="heading">Note</x-slot>
                <textarea x-model="noteText" rows="5"
                    placeholder="Add a note about this competitor…"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm py-2 px-3 focus:outline-none focus:ring-1 focus:ring-primary-500 resize-none"></textarea>
                <x-slot name="footerActions">
                    <x-filament::button x-on:click="$wire.saveNote(noteResultId, noteText)">Save</x-filament::button>
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'note-modal' })">Cancel</x-filament::button>
                </x-slot>
            </x-filament::modal>
        </div>
    </div>
</div>
