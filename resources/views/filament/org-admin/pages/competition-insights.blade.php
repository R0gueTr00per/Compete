<x-filament-panels::page>
    @if ($insight)
        {{-- Metadata bar --}}
        <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/40 px-3 py-1 text-xs font-medium text-primary-700 dark:text-primary-300">
                <x-heroicon-o-sparkles class="w-3.5 h-3.5" />
                AI Insights
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                Generated {{ $insight->generated_at->diffForHumans() }}
            </span>
            <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[0.65rem] font-mono font-semibold bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500">
                {{ $insight->model_used }}
            </span>
        </div>

        {{-- Sections --}}
        <div class="space-y-4">
            @php
                $raw      = $insight->content;
                $sections = preg_split('/(?=^## )/m', $raw, -1, PREG_SPLIT_NO_EMPTY);
            @endphp

            @foreach ($sections as $idx => $section)
                @php
                    $lines       = explode("\n", trim($section), 2);
                    $heading     = trim($lines[0] ?? '');
                    $body        = trim($lines[1] ?? '');
                    $headingText = ltrim($heading, '# ');
                @endphp

                <x-filament::section class="border-l-4 border-l-primary-400 dark:border-l-primary-500">
                    <x-slot name="heading">
                        <span class="text-gray-900 dark:text-gray-100 font-semibold">{{ $headingText }}</span>
                    </x-slot>
                    @if (str_starts_with($heading, '##'))
                        <x-slot name="headerEnd">
                            <button
                                wire:click="openCreateTaskModal(@js($headingText), @js($body))"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-2.5 py-1 text-xs font-medium text-gray-500 dark:text-gray-400 hover:border-primary-300 dark:hover:border-primary-700 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                title="Create task from this section"
                            >
                                <x-heroicon-o-plus-circle class="w-3.5 h-3.5" />
                                Add task
                            </button>
                        </x-slot>
                    @endif

                    <div class="prose prose-sm dark:prose-invert max-w-none
                                prose-p:my-1 prose-ul:my-1 prose-li:my-0.5
                                prose-strong:font-semibold
                                [&_ul]:list-disc [&_ul]:pl-5
                                [&_ol]:list-decimal [&_ol]:pl-5">
                        {!! \Illuminate\Support\Str::markdown($body) !!}
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @else
        <x-filament::section class="border-l-4 border-l-primary-200 dark:border-l-primary-900">
            <div class="flex flex-col items-center justify-center py-16 text-center gap-5">
                <div class="relative">
                    <div class="absolute inset-0 rounded-full bg-primary-100 dark:bg-primary-900/30 blur-xl scale-150 opacity-60"></div>
                    <div class="relative rounded-full bg-primary-50 dark:bg-primary-950/60 border border-primary-100 dark:border-primary-800 p-4">
                        <x-heroicon-o-sparkles class="w-10 h-10 text-primary-400 dark:text-primary-500" />
                    </div>
                </div>
                <div>
                    <p class="text-gray-700 dark:text-gray-300 font-semibold text-base">No insights generated yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-1 max-w-xs mx-auto">
                        Click <strong class="text-gray-700 dark:text-gray-300">Generate Insights</strong> above to analyse this competition's data with AI.
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Create task modal --}}
    @if ($creatingTaskFromHeading)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data
            x-init="$nextTick(() => $refs.taskTitle?.focus())"
        >
            <div class="absolute inset-0 bg-black/50 dark:bg-black/70" wire:click="cancelTaskFromHeading"></div>
            <div class="relative z-10 w-full max-w-lg bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-o-clipboard-document-check class="w-4 h-4 text-primary-500" />
                        Create task
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Title</label>
                        <input
                            type="text"
                            x-ref="taskTitle"
                            wire:model="newTaskFromHeading.title"
                            wire:keydown.escape="cancelTaskFromHeading"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1.5">Notes</label>
                        <textarea
                            wire:model="newTaskFromHeading.notes"
                            rows="8"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                        ></textarea>
                    </div>

                    <div class="flex gap-2 justify-end pt-1">
                        <x-filament::button color="gray" wire:click="cancelTaskFromHeading">Cancel</x-filament::button>
                        <x-filament::button wire:click="saveTaskFromHeading">Create task</x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
