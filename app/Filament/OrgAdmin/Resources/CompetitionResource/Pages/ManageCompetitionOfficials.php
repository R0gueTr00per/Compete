<?php

namespace App\Filament\OrgAdmin\Resources\CompetitionResource\Pages;

use App\Filament\OrgAdmin\Resources\CompetitionResource;
use App\Models\CompetitionOfficial;
use App\Models\OfficialRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\Width;

class ManageCompetitionOfficials extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CompetitionResource::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-identification';
    protected string $view = 'filament.admin.pages.officials-board';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public static function getNavigationLabel(): string
    {
        return 'Officials';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — Officials';
    }

    public function getBreadcrumb(): string
    {
        return 'Officials';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addOfficial')
                ->label('Add Official')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Select::make('user_id')
                        ->label('User')
                        ->required()
                        ->searchable()
                        ->options(function () {
                            $assigned = CompetitionOfficial::where('competition_id', $this->getRecord()->id)
                                ->pluck('user_id');

                            return User::whereHas('ownedProfiles')
                                ->whereNotIn('id', $assigned)
                                ->get()
                                ->mapWithKeys(fn (User $u) => [$u->id => $u->getFilamentName()])
                                ->toArray();
                        }),

                    Select::make('official_role_id')
                        ->label('Role')
                        ->required()
                        ->options(fn () => OfficialRole::where('organisation_id', app('tenant')?->id)->orderBy('name')->pluck('name', 'id')->toArray())
                        ->getOptionLabelUsing(fn ($value) => OfficialRole::find($value)?->name)
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Role name')
                                ->required()
                                ->maxLength(100)
                                ->unique(OfficialRole::class, 'name'),
                        ])
                        ->createOptionUsing(fn (array $data) => OfficialRole::create([
                            'name'            => $data['name'],
                            'organisation_id' => app('tenant')?->id,
                        ])->id),

                    Select::make('competition_location_id')
                        ->label('Location')
                        ->placeholder('None')
                        ->nullable()
                        ->options(
                            fn () => $this->getRecord()
                                ->competitionLocations()
                                ->pluck('name', 'id')
                                ->toArray()
                        ),
                ])
                ->action(function (array $data) {
                    $competition = $this->getRecord();

                    $exists = CompetitionOfficial::where('competition_id', $competition->id)
                        ->where('user_id', $data['user_id'])
                        ->exists();

                    if ($exists) {
                        Notification::make()->warning()->title('This user is already an official for this competition.')->send();
                        return;
                    }

                    CompetitionOfficial::create([
                        'competition_id'          => $competition->id,
                        'user_id'                 => $data['user_id'],
                        'official_role_id'        => $data['official_role_id'],
                        'competition_location_id' => $data['competition_location_id'] ?? null,
                    ]);

                    Notification::make()->success()->title('Official added.')->send();
                }),

            Action::make('back')
                ->label('Back to competition')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => CompetitionResource::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    protected function getViewData(): array
    {
        $competition = $this->getRecord();

        $columns = collect($competition->locations ?? [])
            ->filter()
            ->values()
            ->toArray();

        $officials = CompetitionOfficial::where('competition_id', $competition->id)
            ->with(['user', 'officialRole', 'location'])
            ->get();

        $grouped = ['__unassigned__' => []];
        foreach ($columns as $col) {
            $grouped[$col] = [];
        }

        foreach ($officials as $official) {
            $locationName = $official->location?->name;
            if ($locationName && array_key_exists($locationName, $grouped)) {
                $grouped[$locationName][] = $official;
            } else {
                $grouped['__unassigned__'][] = $official;
            }
        }

        return [
            'columns'           => $columns,
            'officialsByColumn' => $grouped,
        ];
    }

    public function moveOfficial(int $officialId, string $location): void
    {
        $competition = $this->getRecord();

        $official = CompetitionOfficial::where('id', $officialId)
            ->where('competition_id', $competition->id)
            ->first();

        if (! $official) {
            return;
        }

        if ($location === '__unassigned__') {
            $official->update(['competition_location_id' => null]);
        } else {
            $loc = $competition->competitionLocations()->where('name', $location)->first();
            if ($loc) {
                $official->update(['competition_location_id' => $loc->id]);
            }
        }
    }

    public function removeOfficialAction(): Action
    {
        return Action::make('removeOfficial')
            ->requiresConfirmation()
            ->modalHeading('Remove official?')
            ->modalDescription(fn (array $arguments) => "Remove {$arguments['name']} as an official from this competition?")
            ->modalSubmitActionLabel('Remove')
            ->color('danger')
            ->action(function (array $arguments) {
                CompetitionOfficial::where('id', (int) $arguments['officialId'])
                    ->where('competition_id', $this->getRecord()->id)
                    ->delete();

                Notification::make()->success()->title('Official removed.')->send();
            });
    }
}
