<?php

namespace JaysonTemporas\PageBookmarks;

use DutchCodingCompany\FilamentDeveloperLogins\FilamentDeveloperLoginsPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use JaysonTemporas\PageBookmarks\Livewire\BookmarkManager;
use JaysonTemporas\PageBookmarks\Livewire\BookmarkViewer;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PageBookmarksServiceProvider extends PackageServiceProvider
{
    public static string $name = 'page-bookmarks';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasMigration('create_bookmarks_table')
            ->hasViews(static::$name)
            // Publishing groups
            ->hasTranslations()
            ->hasInstallCommand(function ($command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->publishAssets()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted(): void
    {
        // Register the Livewire component
        Livewire::component('page-bookmarks::livewire.bookmark-manager', BookmarkManager::class);
        Livewire::component('page-bookmarks::livewire.bookmark-viewer', BookmarkViewer::class);

        self::registerRenderHooks();
    }

    protected static function registerRenderHooks(): void
    {
        FilamentView::registerRenderHook(
            config('page-bookmarks.render_hooks.add_bookmark', PanelsRenderHook::GLOBAL_SEARCH_AFTER),
            static function (): ?string {
                if (! static::isPluginEnabled(PageBookmarksPlugin::ID)) {
                    return null;
                }

                return Blade::render("@livewire('page-bookmarks::livewire.bookmark-manager')");
            },
        );

        FilamentView::registerRenderHook(
            config('page-bookmarks.render_hooks.view_bookmarks', PanelsRenderHook::GLOBAL_SEARCH_AFTER),
            static function (): ?string {
                if (! static::isPluginEnabled(PageBookmarksPlugin::ID)) {
                    return null;
                }

                return Blade::render("@livewire('page-bookmarks::livewire.bookmark-viewer')");
            },
        );
    }

    protected static function panelHasPlugin(?Panel $panel): bool
    {
        return ! is_null($panel) && $panel->hasPlugin(PageBookmarksPlugin::ID);
    }

    protected static function isPluginEnabled(string $plugin): bool
    {
        /** @var Panel $panel */
        $panel = Filament::getCurrentPanel();
        if (! self::panelHasPlugin($panel)) {
            return false;
        }

        /** @var PageBookmarksPlugin $plugin */
        $plugin = $panel->getPlugin($plugin);

        return $plugin->getEnabled();
    }
}
