<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Models\CompetitionTask;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use App\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageCompetitionTasks extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string $view = 'filament.org-admin.pages.competition-tasks';

    public array $newTask = ['title' => '', 'notes' => ''];
    public bool $addingTask = false;

    public ?int $editingTaskId = null;
    public array $editingTask = ['title' => '', 'notes' => ''];

    public ?int $confirmingDeleteTaskId = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Tasks';
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Tasks';
    }

    public function getBreadcrumb(): string
    {
        return 'Tasks';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    public function getTasks()
    {
        return $this->getRecord()->tasks()
            ->orderBy('completed')
            ->orderBy('sort_order')
            ->get();
    }

    public function getPendingCount(): int
    {
        return $this->getRecord()->tasks()->where('completed', false)->count();
    }

    public function startAdding(): void
    {
        $this->newTask = ['title' => '', 'notes' => ''];
        $this->addingTask = true;
    }

    public function cancelAdding(): void
    {
        $this->addingTask = false;
        $this->newTask = ['title' => '', 'notes' => ''];
    }

    public function saveNewTask(): void
    {
        $title = trim($this->newTask['title'] ?? '');

        if ($title === '') {
            $this->addError('newTask.title', 'Title is required.');
            return;
        }

        $maxOrder = $this->getRecord()->tasks()->max('sort_order') ?? -1;

        CompetitionTask::create([
            'competition_id' => $this->getRecord()->id,
            'title'          => $title,
            'notes'          => trim($this->newTask['notes'] ?? '') ?: null,
            'sort_order'     => $maxOrder + 1,
        ]);

        $this->addingTask = false;
        $this->newTask = ['title' => '', 'notes' => ''];
    }

    public function startEditing(int $taskId): void
    {
        $task = CompetitionTask::find($taskId);
        if (! $task || $task->competition_id !== $this->getRecord()->id) return;

        $this->editingTaskId = $taskId;
        $this->editingTask = ['title' => $task->title, 'notes' => $task->notes ?? ''];
    }

    public function saveEdit(): void
    {
        $task = CompetitionTask::find($this->editingTaskId);
        if (! $task || $task->competition_id !== $this->getRecord()->id) return;

        $title = trim($this->editingTask['title'] ?? '');
        if ($title === '') return;

        $task->update([
            'title' => $title,
            'notes' => trim($this->editingTask['notes'] ?? '') ?: null,
        ]);

        $this->editingTaskId = null;
        $this->editingTask = ['title' => '', 'notes' => ''];
    }

    public function cancelEdit(): void
    {
        $this->editingTaskId = null;
        $this->editingTask = ['title' => '', 'notes' => ''];
    }

    public function toggleComplete(int $taskId): void
    {
        $task = CompetitionTask::find($taskId);
        if (! $task || $task->competition_id !== $this->getRecord()->id) return;

        $task->update([
            'completed'    => ! $task->completed,
            'completed_at' => ! $task->completed ? now() : null,
        ]);
    }

    public function moveUp(int $taskId): void
    {
        $tasks = $this->getRecord()->tasks()->get();
        $index = $tasks->search(fn ($t) => $t->id === $taskId);

        if ($index === false || $index === 0) return;

        $current = $tasks[$index];
        $above   = $tasks[$index - 1];

        [$current->sort_order, $above->sort_order] = [$above->sort_order, $current->sort_order];
        $current->save();
        $above->save();
    }

    public function moveDown(int $taskId): void
    {
        $tasks = $this->getRecord()->tasks()->get();
        $index = $tasks->search(fn ($t) => $t->id === $taskId);

        if ($index === false || $index >= $tasks->count() - 1) return;

        $current = $tasks[$index];
        $below   = $tasks[$index + 1];

        [$current->sort_order, $below->sort_order] = [$below->sort_order, $current->sort_order];
        $current->save();
        $below->save();
    }

    public function confirmDeleteTask(int $taskId): void
    {
        $this->confirmingDeleteTaskId = $taskId;
    }

    public function cancelDeleteTask(): void
    {
        $this->confirmingDeleteTaskId = null;
    }

    public function deleteTask(int $taskId): void
    {
        $task = CompetitionTask::find($taskId);
        if (! $task || $task->competition_id !== $this->getRecord()->id) return;

        $task->delete();
        $this->confirmingDeleteTaskId = null;
    }
}
