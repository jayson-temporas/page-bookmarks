<?php

namespace JaysonTemporas\PageBookmarks;

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
            ->name('page-bookmarks')
            ->hasConfigFile()
            ->hasMigration('create_bookmarks_table')
            ->hasViews('page-bookmarks')
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

        FilamentView::registerRenderHook(
            config('page-bookmarks.render_hooks.add_bookmark', PanelsRenderHook::GLOBAL_SEARCH_AFTER),
            fn (): string => Blade::render("@livewire('page-bookmarks::livewire.bookmark-manager')"),
        );

        FilamentView::registerRenderHook(
            config('page-bookmarks.render_hooks.view_bookmarks', PanelsRenderHook::GLOBAL_SEARCH_AFTER),
            fn (): string => Blade::render("@livewire('page-bookmarks::livewire.bookmark-viewer')"),
        );
    }
}
