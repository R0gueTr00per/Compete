<?php

use Carbon\Carbon;

/**
 * Format a date value using the current tenant's date_format preference.
 */
function tenant_date(mixed $date): string
{
    if (! $date) return '—';
    $format = app('tenant')?->date_format ?? 'd M Y';
    return Carbon::parse($date)->format($format);
}

/**
 * Format a datetime value using the tenant's date_format and timezone.
 */
function tenant_datetime(mixed $datetime): string
{
    if (! $datetime) return '—';
    $org    = app('tenant');
    $format = ($org?->date_format ?? 'd M Y') . ' g:i a';
    $carbon = Carbon::parse($datetime);
    if ($org?->timezone) {
        $carbon = $carbon->setTimezone($org->timezone);
    }
    return $carbon->format($format);
}

/**
 * Format a time value applying the tenant's timezone.
 */
function tenant_time(mixed $time): string
{
    if (! $time) return '—';
    $org    = app('tenant');
    $carbon = Carbon::parse($time);
    if ($org?->timezone) {
        $carbon = $carbon->setTimezone($org->timezone);
    }
    return $carbon->format('g:i a');
}

/**
 * Format a monetary amount using the tenant's currency setting.
 * Returns the symbol + formatted number (e.g. "$12.50", "€12.50").
 */
function tenant_money(mixed $amount): string
{
    if ($amount === null || $amount === '') return '—';
    $symbol = tenant_currency_symbol();
    return $symbol . number_format((float) $amount, 2);
}

/**
 * Return the ISO currency code for the current tenant (for Filament ->money()).
 */
function tenant_currency(): string
{
    return app('tenant')?->currency ?? 'AUD';
}

/**
 * Return a display symbol derived from the tenant's currency code.
 */
function tenant_currency_symbol(): string
{
    $code = strtoupper(app('tenant')?->currency ?? 'AUD');

    $symbols = [
        'AUD' => '$',
        'USD' => '$',
        'CAD' => '$',
        'NZD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'JPY' => '¥',
        'CNY' => '¥',
        'SGD' => '$',
        'HKD' => '$',
    ];

    return $symbols[$code] ?? $code . ' ';
}

/**
 * Return the PHP date format string for the current tenant.
 */
function tenant_date_format(): string
{
    return app('tenant')?->date_format ?? 'd M Y';
}

/**
 * Return a Carbon instance with the tenant's timezone applied.
 */
function tenant_now(): Carbon
{
    $tz = app('tenant')?->timezone;
    return $tz ? Carbon::now($tz) : Carbon::now();
}
