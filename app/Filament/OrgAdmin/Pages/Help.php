<?php

namespace App\Filament\OrgAdmin\Pages;

use Filament\Pages\Page;

class Help extends Page
{
    protected static ?string $title            = 'Help';
    protected static ?string $navigationIcon   = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel  = 'Help';
    protected static ?string $navigationGroup  = 'Account';
    protected static ?int    $navigationSort   = 195;
    protected static string  $view             = 'filament.org-admin.pages.help';
}
