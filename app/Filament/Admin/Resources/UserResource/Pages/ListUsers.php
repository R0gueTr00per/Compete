<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make('Pending approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => \App\Models\User::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),

            'inactive' => Tab::make('Inactive')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'inactive')),

            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
