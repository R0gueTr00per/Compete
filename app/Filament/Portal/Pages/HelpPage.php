<?php

namespace App\Filament\Portal\Pages;

use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $title            = 'Help';
    protected static ?string $navigationIcon   = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationLabel  = 'Help';
    protected static ?string $navigationGroup  = 'Account';
    protected static ?int    $navigationSort   = 195;
    protected static string  $view             = 'filament.portal.pages.help-page';
    protected static ?string $slug             = 'help';
}
