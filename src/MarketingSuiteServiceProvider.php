<?php

namespace VasilGerginski\MarketingSuite;

use AshAllenDesign\ShortURL\Events\ShortURLVisited;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use VasilGerginski\MarketingSuite\Commands\MarketingSuiteCommand;
use VasilGerginski\MarketingSuite\Settings\SiteSettings;
use VasilGerginski\MarketingSuite\Testing\TestsMarketingSuite;

class MarketingSuiteServiceProvider extends PackageServiceProvider
{
    public static string $name = 'marketing-suite';

    public static string $viewNamespace = 'marketing-suite';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasRoute('web')
            ->hasMigrations($this->getMigrations());
    }

    public function packageRegistered(): void
    {
        // Register the package settings class so it works without the host
        // app having to add it to its own config/settings.php.
        $this->app['config']->set(
            'settings.settings',
            array_unique(array_merge(
                $this->app['config']->get('settings.settings', []),
                [SiteSettings::class],
            )),
        );

        // Keep generated short links actually short (e.g. yoursite.com/s/Xk3aP).
        // Set marketing-suite.short_urls.prefix to null to keep the prefix
        // configured in the short-url package instead. The fallback covers
        // hosts that published this config before the prefix key existed:
        // Laravel's config merge is not recursive, so their short_urls array
        // shadows the packaged default and the key comes back missing.
        if ($prefix = $this->app['config']->get('marketing-suite.short_urls.prefix', '/s')) {
            $this->app['config']->set('short-url.prefix', $prefix);
        }

        // The redirect route needs the web middleware group for a session —
        // conversion tracking stores the visit id in the session so landing
        // page forms can attach it to the lead.
        if (empty($this->app['config']->get('short-url.middleware'))) {
            $this->app['config']->set('short-url.middleware', ['web']);
        }
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName(),
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName(),
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/marketing-suite/{$file->getFilename()}"),
                ], 'marketing-suite-stubs');
            }
        }

        // Blade components shipped with the package (<x-blog-card>, layouts).
        // Host apps can override them by defining components with the same name.
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components');

        // Remember which short URL visit brought the visitor here — the
        // landing page forms attach it to the lead, which is what the
        // conversion metrics count.
        Event::listen(ShortURLVisited::class, static function (ShortURLVisited $event): void {
            session(['short_url_visit_id' => $event->shortURLVisit->id]);
        });

        // Testing
        Testable::mixin(new TestsMarketingSuite);

        // Register Livewire Components
        $this->registerLivewireComponents();
    }

    protected function getAssetPackageName(): ?string
    {
        return 'vasilgerginski/marketing-suite';
    }

    protected function getAssets(): array
    {
        return [
            Css::make('marketing-suite-styles', __DIR__ . '/../resources/dist/marketing-suite.css'),
        ];
    }

    protected function getCommands(): array
    {
        return [
            MarketingSuiteCommand::class,
        ];
    }

    protected function getIcons(): array
    {
        return [];
    }

    protected function getRoutes(): array
    {
        return [];
    }

    protected function getScriptData(): array
    {
        return [];
    }

    protected function getMigrations(): array
    {
        return [
            'create_authors_table',
            'create_blog_posts_table',
            'create_definitions_table',
            'create_faq_items_table',
            'create_help_categories_table',
            'create_help_articles_table',
            'create_help_article_feedback_table',
            'create_newsletter_subscribers_table',
            'create_events_table',
            'create_event_slots_table',
            'create_landing_pages_table',
            'create_event_submissions_table',
            'create_marketing_suite_settings',
            'make_event_submissions_event_id_nullable',
        ];
    }

    protected function registerLivewireComponents(): void
    {
        // Livewire v4 resolves "namespace::name" components through registered
        // namespaces (kebab-case dotted names map onto studly classes), so a
        // single namespace registration covers every component in src/Livewire.
        Livewire::addNamespace(
            'marketing-suite',
            classNamespace: 'VasilGerginski\\MarketingSuite\\Livewire',
        );
    }
}
