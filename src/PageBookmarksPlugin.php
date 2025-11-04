<?php

namespace JaysonTemporas\PageBookmarks;

use Filament\Contracts\Plugin;
use Filament\Panel;
use \Closure;
use Filament\Support\Concerns\EvaluatesClosures;

class PageBookmarksPlugin implements Plugin
{
    use EvaluatesClosures;

    public Closure | bool $enabled = true;

    const string ID = 'jaysontemporas-page-bookmarks';

    public function getId(): string
    {
        return static::ID;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([

            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function enabled(Closure | bool $value = true): static
    {
        $this->enabled = $value;

        return $this;
    }

    public function getEnabled(): bool
    {
        return $this->evaluate($this->enabled);
    }

    public static function make(): static
    {
        return new static;
    }
}
