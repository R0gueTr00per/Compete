<x-filament-panels::page>
    @if ($insight)
        <div class="space-y-4">
            {{-- Generated timestamp --}}
            <div class="flex items-center justify-between flex-wrap gap-2">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Generated {{ $insight->generated_at->diffForHumans() }}
                    &bull; {{ $insight->model_used }}
                </p>
            </div>

            {{-- Insight content rendered as markdown sections --}}
            <div class="prose prose-sm dark:prose-invert max-w-none
                        prose-headings:text-gray-900 dark:prose-headings:text-gray-100
                        prose-h2:text-base prose-h2:font-semibold prose-h2:mt-6 prose-h2:mb-2
                        prose-ul:my-1 prose-li:my-0.5
                        prose-p:my-1">
                @php
                    $raw      = $insight->content;
                    $sections = preg_split('/(?=^## )/m', $raw, -1, PREG_SPLIT_NO_EMPTY);
                @endphp

                @foreach ($sections as $section)
                    @php
                        $lines       = explode("\n", trim($section), 2);
                        $heading     = trim($lines[0] ?? '');
                        $body        = trim($lines[1] ?? '');
                        $headingText = ltrim($heading, '# ');
                    @endphp

                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center justify-between w-full gap-2">
                                <span class="text-gray-800 dark:text-gray-100 font-semibold">{{ $headingText }}</span>
                                @if (str_starts_with($heading, '##'))
                                <button
                                    wire:click="openCreateTaskModal(@js($headingText), @js($body))"
                                    class="flex-shrink-0 inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    title="Create task from this section"
                                >
                                    <x-heroicon-o-plus-circle class="w-3.5 h-3.5" />
                                    Task
                                </button>
                                @endif
                            </div>
                        </x-slot>

                        <div class="prose prose-sm dark:prose-invert max-w-none
                                    prose-p:my-1 prose-ul:my-1 prose-li:my-0.5
                                    prose-strong:font-semibold">
                            {!! \Illuminate\Support\Str::markdown($body) !!}
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        </div>
    @else
        <x-filament::section>
            <div class="flex flex-col items-center justify-center py-12 text-center gap-4">
                <x-heroicon-o-sparkles class="w-12 h-12 text-gray-300 dark:text-gray-600" />
                <div>
                    <p class="text-gray-600 dark:text-gray-400 font-medium">No insights generated yet</p>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                        Click <strong>Generate Insights</strong> above to analyse this competition's data.
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
            <div class="relative z-10 w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 space-y-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Create task</h3>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                    <input
                        type="text"
                        x-ref="taskTitle"
                        wire:model="newTaskFromHeading.title"
                        wire:keydown.escape="cancelTaskFromHeading"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes</label>
                    <textarea
                        wire:model="newTaskFromHeading.notes"
                        rows="8"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    ></textarea>
                </div>

                <div class="flex gap-2 justify-end">
                    <x-filament::button color="gray" wire:click="cancelTaskFromHeading">Cancel</x-filament::button>
                    <x-filament::button wire:click="saveTaskFromHeading">Create task</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
