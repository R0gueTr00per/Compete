<x-filament-panels::page>
    @php
        $tasks        = $this->getTasks();
        $pendingCount = $this->getPendingCount();
        $pending      = $tasks->where('completed', false)->values();
    @endphp

    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">
                Tasks
                @if ($pendingCount > 0)
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300">
                        {{ $pendingCount }} pending
                    </span>
                @endif
            </x-slot>

            <x-slot name="headerEnd">
                @if (! $addingTask)
                    <x-filament::button size="sm" wire:click="startAdding" icon="heroicon-o-plus">
                        Add task
                    </x-filament::button>
                @endif
            </x-slot>

            {{-- Add task form --}}
            @if ($addingTask)
                <div class="mb-4 p-4 rounded-lg border border-primary-200 dark:border-primary-700 bg-primary-50/40 dark:bg-primary-900/10 space-y-3">
                    <div>
                        <input
                            type="text"
                            wire:model="newTask.title"
                            wire:keydown.enter="saveNewTask"
                            wire:keydown.escape="cancelAdding"
                            placeholder="Task title"
                            autofocus
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                        />
                        @error('newTask.title')
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <textarea
                        wire:model="newTask.notes"
                        placeholder="Notes (optional)"
                        rows="2"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                    ></textarea>
                    <div class="flex gap-2">
                        <x-filament::button size="sm" wire:click="saveNewTask">Save</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="cancelAdding">Cancel</x-filament::button>
                    </div>
                </div>
            @endif

            @if ($tasks->isEmpty() && ! $addingTask)
                <p class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">No tasks yet. Click <strong>Add task</strong> to get started.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($tasks as $i => $task)
                        @php
                            $isPending    = ! $task->completed;
                            $pendingIndex = $pending->search(fn ($t) => $t->id === $task->id);
                        @endphp
                        <li class="py-3" wire:key="task-{{ $task->id }}">

                            @if ($editingTaskId === $task->id)
                                {{-- Edit mode: full-width, no side controls --}}
                                <div class="space-y-2">
                                    <input
                                        type="text"
                                        wire:model="editingTask.title"
                                        wire:keydown.enter="saveEdit"
                                        wire:keydown.escape="cancelEdit"
                                        autofocus
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                                    />
                                    <textarea
                                        wire:model="editingTask.notes"
                                        placeholder="Notes (optional)"
                                        rows="2"
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
                                    ></textarea>
                                    <div class="flex gap-2">
                                        <x-filament::button size="sm" wire:click="saveEdit">Save</x-filament::button>
                                        <x-filament::button size="sm" color="gray" wire:click="cancelEdit">Cancel</x-filament::button>
                                    </div>
                                </div>
                            @else
                                {{-- View mode --}}
                                <div class="flex items-start gap-3">

                                    {{-- Reorder buttons (pending only) --}}
                                    @if ($isPending)
                                        <div class="flex flex-col gap-0.5 mt-0.5 flex-shrink-0">
                                            <button
                                                wire:click="moveUp({{ $task->id }})"
                                                @if ($pendingIndex === 0) disabled @endif
                                                class="p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-20 disabled:cursor-not-allowed"
                                                title="Move up"
                                            >
                                                <x-heroicon-s-chevron-up class="w-3.5 h-3.5" />
                                            </button>
                                            <button
                                                wire:click="moveDown({{ $task->id }})"
                                                @if ($pendingIndex === $pending->count() - 1) disabled @endif
                                                class="p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-20 disabled:cursor-not-allowed"
                                                title="Move down"
                                            >
                                                <x-heroicon-s-chevron-down class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    @else
                                        {{-- Spacer to align with pending rows --}}
                                        <div class="w-4 flex-shrink-0"></div>
                                    @endif

                                    {{-- Checkbox --}}
                                    @if ($isPending)
                                        <button
                                            wire:click="toggleComplete({{ $task->id }})"
                                            class="mt-0.5 flex-shrink-0 w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-500 hover:border-success-500 dark:hover:border-success-400 transition-colors"
                                            title="Mark complete"
                                        ></button>
                                    @else
                                        <button
                                            wire:click="toggleComplete({{ $task->id }})"
                                            class="mt-0.5 flex-shrink-0 w-5 h-5 rounded border-2 border-success-500 bg-success-500 dark:border-success-600 dark:bg-success-600 hover:bg-success-400 hover:border-success-400 transition-colors flex items-center justify-center"
                                            title="Mark incomplete"
                                        >
                                            <svg class="w-3 h-3 text-white" viewBox="0 0 12 12" fill="none">
                                                <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    @endif

                                    {{-- Content --}}
                                    <div class="flex-1 min-w-0">
                                        @if ($isPending)
                                            <button
                                                class="text-sm font-medium text-gray-800 dark:text-gray-100 text-left hover:text-primary-600 dark:hover:text-primary-400 w-full"
                                                wire:click="startEditing({{ $task->id }})"
                                            >
                                                {{ $task->title }}
                                            </button>
                                            @if ($task->notes)
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $task->notes }}</p>
                                            @endif
                                        @else
                                            <p class="text-sm text-gray-400 dark:text-gray-500 line-through">{{ $task->title }}</p>
                                            @if ($task->notes)
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 line-through">{{ $task->notes }}</p>
                                            @endif
                                            @if ($task->completed_at)
                                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Done {{ $task->completed_at->diffForHumans() }}</p>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- Delete --}}
                                    <button
                                        wire:click="confirmDeleteTask({{ $task->id }})"
                                        class="flex-shrink-0 mt-0.5 p-1 rounded text-gray-300 hover:text-danger-500 dark:text-gray-600 dark:hover:text-danger-400 transition-colors"
                                        title="Delete"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>

                                </div>
                            @endif

                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    </div>

    {{-- Delete task confirmation modal --}}
    @if ($confirmingDeleteTaskId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50 dark:bg-black/70" wire:click="cancelDeleteTask"></div>
            <div class="relative z-10 w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 space-y-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Delete task?</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">This cannot be undone.</p>
                <div class="flex gap-2 justify-end">
                    <x-filament::button color="gray" wire:click="cancelDeleteTask">Cancel</x-filament::button>
                    <x-filament::button color="danger" wire:click="deleteTask({{ $confirmingDeleteTaskId }})">Delete</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
