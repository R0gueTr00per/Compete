<div x-data="{}">
    @if ($this->rollcallRequired)
        {{-- Step 1: Rollcall rows --}}
        @php $rollcall = $this->getRollcallRows(); @endphp
        @if ($rollcall->isEmpty())
            <p class="text-center text-sm text-gray-400 py-4">No checked-in competitors in this division.</p>
        @else
            @php $activeEeIds = $rollcall->where('absent', false)->pluck('ee_id')->values()->all(); @endphp
            <div x-data="{
                    present: {{ json_encode($this->rollcallPresent) }},
                    allActive: {{ json_encode($activeEeIds) }},
                    get allMarked() {
                        return this.allActive.length > 0 && this.allActive.every(id => this.present.includes(id));
                    },
                    toggle(id) {
                        const idx = this.present.indexOf(id);
                        if (idx >= 0) { this.present = this.present.filter(i => i !== id); }
                        else { this.present = [...this.present, id]; }
                    },
                    markAll()   { this.present = [...new Set([...this.present, ...this.allActive])]; },
                    unmarkAll() { this.present = this.present.filter(id => !this.allActive.includes(id)); }
                 }"
                 x-on:begin-scoring-pressed.window="$wire.call('beginScoring', present)">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs text-gray-400">Tap each competitor to confirm they are present.</p>
                    <x-filament::button size="xs" color="gray"
                        x-on:click="allMarked ? unmarkAll() : markAll()"
                        x-text="allMarked ? 'Unmark all present' : 'Mark all present'">
                        Mark all present
                    </x-filament::button>
                </div>
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rollcall->where('absent', false) as $rc)
                        <li x-on:click="toggle({{ $rc->ee_id }})"
                            class="flex items-center gap-3 py-2.5 cursor-pointer select-none">
                            <template x-if="present.includes({{ $rc->ee_id }})">
                                <x-heroicon-m-check-circle class="w-6 h-6 text-success-500 shrink-0" />
                            </template>
                            <template x-if="!present.includes({{ $rc->ee_id }})">
                                <div class="w-6 h-6 rounded-full border-2 border-gray-300 dark:border-gray-600 shrink-0"></div>
                            </template>
                            <span class="text-sm"
                                :class="present.includes({{ $rc->ee_id }}) ? 'font-medium text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'">
                                {{ $rc->name }}
                                @if ($rc->info)
                                    <span class="font-normal text-gray-400 dark:text-gray-500">({{ $rc->info }})</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @else
        {{-- No rollcall required — simple Begin Scoring gate --}}
        <div class="flex flex-col items-center justify-center gap-2 py-6 text-center"
             x-on:begin-scoring-pressed.window="$wire.call('beginScoring')">
            <x-heroicon-o-play-circle class="w-10 h-10 text-gray-300 dark:text-gray-600" />
            <p class="text-sm text-gray-500 dark:text-gray-400">All checked-in competitors will be included.</p>
        </div>
    @endif
</div>
