<x-filament-panels::page>
    <div class="space-y-6">
        @livewire(
            \App\Filament\Admin\Resources\CompetitionResource\RelationManagers\CompetitionEventsRelationManager::class,
            ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass]
        )

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @livewire(
                \App\Filament\Admin\Resources\CompetitionResource\RelationManagers\AgeBandsRelationManager::class,
                ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass]
            )

            @livewire(
                \App\Filament\Admin\Resources\CompetitionResource\RelationManagers\RankBandsRelationManager::class,
                ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass]
            )

            @livewire(
                \App\Filament\Admin\Resources\CompetitionResource\RelationManagers\WeightClassesRelationManager::class,
                ['ownerRecord' => $ownerRecord, 'pageClass' => $pageClass]
            )
        </div>
    </div>
</x-filament-panels::page>
