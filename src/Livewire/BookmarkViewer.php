<?php

declare(strict_types=1);

namespace JaysonTemporas\PageBookmarks\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use JaysonTemporas\PageBookmarks\Models\Bookmark;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class BookmarkViewer extends Component
{
    /**
     * Get bookmarks organized by folders
     *
     * @return Collection<string, Collection<int, Bookmark>>
     */
    #[Computed]
    public function bookmarksByFolder(): Collection
    {
        $user = auth()->user();

        if ($user === null) {
            return collect();
        }

        $bookmarks = Bookmark::query()->where('user_id', $user->id)
            ->with('folder')
            ->orderBy('name')
            ->get();

        // Group by bookmark folder (or 'Uncategorized' if folder is null)
        $grouped = $bookmarks->groupBy(function (Bookmark $bookmark) {
            if ($bookmark->folder) {
                return $bookmark->folder->name;
            }

            // Fallback to the old folder field for backward compatibility
            return $bookmark->folder ?: __('page-bookmarks::translation.uncategorized');
        });

        /** @var Collection<string, Collection<int, Bookmark>> */
        $result = collect();

        // Convert to the right Collection types for PHPStan
        foreach ($grouped as $folder => $items) {
            /** @var Collection<int, Bookmark> */
            $bookmarkCollection = collect($items);
            $result->put($folder, $bookmarkCollection);
        }

        return $result;
    }

    /**
     * Delete a bookmark
     */
    public function deleteBookmark(int $id): void
    {
        $bookmark = Bookmark::query()->where('user_id', auth()->id())->find($id);

        if ($bookmark) {
            $bookmark->delete();

            Notification::make()
                ->duration(2000)
                ->title(__('page-bookmarks::translation.bookmark_deleted_successfully'))
                ->success()
                ->send();

            $this->dispatch('refreshBookmarks');
        }
    }

    /**
     * Refresh bookmarks from event
     */
    #[On('refreshBookmarks')]
    public function refreshBookmarks(): void
    {
        // This will refresh the computed property
    }

    /**
     * Get configured icons
     *
     * @return array<string, string>
     */
    public function getIcons(): array
    {
        return config('page-bookmarks.icons', [
            'bookmark_manager' => 'heroicon-o-folder-plus',
            'bookmark_viewer' => 'heroicon-o-bookmark',
            'bookmark_item' => 'heroicon-o-bookmark',
            'folder' => 'heroicon-o-folder',
            'search' => 'heroicon-o-magnifying-glass',
            'delete' => 'heroicon-o-trash',
            'chevron_down' => 'heroicon-o-chevron-down',
            'empty_state' => 'heroicon-o-bookmark',
        ]);
    }

    public function render(): View
    {
        return view('page-bookmarks::livewire.bookmark-viewer');
    }
}
