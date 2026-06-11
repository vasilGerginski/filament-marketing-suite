<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as AuthUser;
use Livewire\Livewire;
use VasilGerginski\MarketingSuite\Filament\Resources\ShortUrlResource\Widgets\VisitStatsWidget;
use VasilGerginski\MarketingSuite\Models\EventSubmission;
use VasilGerginski\MarketingSuite\Models\ShortUrl;
use VasilGerginski\MarketingSuite\Models\ShortUrlVisit;

it('renders the visit stats widget once a conversion exists', function () {
    runPackageMigrations();
    runShortUrlMigrations();

    $user = new class extends AuthUser
    {
        protected $table = 'users';

        protected $guarded = [];
    };
    $user->forceFill(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret'])->save();

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $short = ShortUrl::query()->create([
        'destination_url' => 'https://example.com/landing/test',
        'url_key' => 'stats',
        'default_short_url' => ShortUrl::defaultShortUrlFor('stats'),
        'single_use' => false,
        'forward_query_params' => false,
        'track_visits' => true,
        'track_ip_address' => true,
        'track_browser' => true,
        'track_browser_version' => true,
        'track_operating_system' => true,
        'track_operating_system_version' => true,
        'track_referer_url' => true,
        'track_device_type' => true,
        'activated_at' => now(),
    ]);

    $visit = ShortUrlVisit::query()->create([
        'short_url_id' => $short->id,
        'device_type' => 'desktop',
        'visited_at' => now()->subMinutes(5),
    ]);

    EventSubmission::query()->create([
        'short_url_visit_id' => $visit->id,
        'section_type' => 'lead_form',
        'data' => ['email' => 'lead@example.com'],
    ]);

    // The average-time-to-convert stat only runs once a conversion exists;
    // it must render on every database driver, not just PostgreSQL.
    Livewire::test(VisitStatsWidget::class, ['record' => $short])
        ->assertOk()
        ->assertSee('5m');
});
