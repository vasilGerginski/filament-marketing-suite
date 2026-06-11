<?php

declare(strict_types=1);

namespace VasilGerginski\MarketingSuite\Filament\Resources\ShortUrlResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use VasilGerginski\MarketingSuite\Models\EventSubmission;
use VasilGerginski\MarketingSuite\Models\ShortUrl;
use VasilGerginski\MarketingSuite\Models\ShortUrlVisit;

class VisitStatsWidget extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        $visitsQuery = ShortUrlVisit::query()
            ->where(static function ($q): void {
                $q->whereNull('device_type')->orWhere('device_type', '!=', 'robot');
            });

        $submissionsQuery = EventSubmission::query()
            ->whereNotNull('short_url_visit_id');

        if ($this->record instanceof ShortUrl) {
            $visitsQuery->where('short_url_id', $this->record->id);

            $visitIds = ShortUrlVisit::query()
                ->where('short_url_id', $this->record->id)
                ->select('id');

            $submissionsQuery->whereIn('short_url_visit_id', $visitIds);
        }

        $totalVisits = $visitsQuery->count();
        $uniqueIps = (clone $visitsQuery)->distinct('ip_address')->count('ip_address');
        $totalConversions = $submissionsQuery->count();

        $conversionRate = $totalVisits > 0
            ? round(($totalConversions / $totalVisits) * 100, 1)
            : 0.0;

        // Average time to convert, computed in PHP: epoch arithmetic has no
        // portable SQL spelling (EXTRACT(EPOCH ...) is PostgreSQL-only and
        // takes the whole widget down on MySQL and SQLite).
        $avgTimeToConvert = null;
        if ($totalConversions > 0) {
            $avgSeconds = (clone $submissionsQuery)
                ->join('short_url_visits', 'short_url_visits.id', '=', 'event_submissions.short_url_visit_id')
                ->whereNotNull('short_url_visits.visited_at')
                ->toBase()
                ->get(['event_submissions.created_at as converted_at', 'short_url_visits.visited_at as visited_at'])
                ->map(static fn (object $row): int => Carbon::parse($row->converted_at)->getTimestamp() - Carbon::parse($row->visited_at)->getTimestamp())
                ->avg();

            if ($avgSeconds !== null && $avgSeconds > 0) {
                $avgMinutes = round($avgSeconds / 60);
                $avgTimeToConvert = $avgMinutes < 60
                    ? $avgMinutes . __('m')
                    : round($avgMinutes / 60, 1) . __('h');
            }
        }

        // Cost per conversion
        $costPerConversion = null;
        $costDescription = __('Set campaign cost to track ROI');
        if ($this->record instanceof ShortUrl && $this->record->price > 0) {
            if ($totalConversions > 0) {
                $cost = round($this->record->price / $totalConversions, 2);
                $currency = $this->record->currency ?? 'EUR';
                $costPerConversion = number_format($cost, 2) . ' ' . $currency;
                $costDescription = __(':cost per conversion', ['cost' => $this->record->price . ' ' . $currency]);
            } else {
                $costPerConversion = __('No conversions');
                $costDescription = __('Campaign cost: :cost', ['cost' => $this->record->price . ' ' . ($this->record->currency ?? 'EUR')]);
            }
        }

        return [
            Stat::make(__('Total Visits'), number_format($totalVisits))
                ->description(__('Unique IPs: :count', ['count' => number_format($uniqueIps)]))
                ->color('primary'),

            Stat::make(__('Conversions'), number_format($totalConversions))
                ->description($conversionRate . '% ' . __('conversion rate'))
                ->color($totalConversions > 0 ? 'success' : 'gray'),

            Stat::make(__('Avg. Time to Convert'), $avgTimeToConvert ?? __('N/A'))
                ->description(__('From visit to booking'))
                ->color($avgTimeToConvert ? 'info' : 'gray'),

            Stat::make(__('Cost Per Conversion'), $costPerConversion ?? __('No cost set'))
                ->description($costDescription)
                ->color($costPerConversion && $costPerConversion !== __('No conversions') ? 'warning' : 'gray'),
        ];
    }
}
