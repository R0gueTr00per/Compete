<?php

namespace App\Filament\OrgAdmin\Pages;

use Filament\Pages\Page;

class Help extends Page
{
    protected static ?string $title            = 'Help';
    protected static string | \BackedEnum | null $navigationIcon   = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel  = 'Help';
    protected static string | \UnitEnum | null $navigationGroup  = 'Account';
    protected static ?int    $navigationSort   = 195;
    protected string $view             = 'filament.org-admin.pages.help';
}
