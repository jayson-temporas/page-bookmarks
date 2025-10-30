<?php

declare(strict_types=1);

namespace JaysonTemporas\PageBookmarks\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use JaysonTemporas\PageBookmarks\Models\Bookmark;
use JaysonTemporas\PageBookmarks\Models\BookmarkFolder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
#[On('refreshBookmarks')]
class BookmarkViewer extends Component implements HasActions, HasForms
{
    use InteractsWithForms;
    use InteractsWithActions;

    /**
     * Get bookmarks organized by folders
     *
     * @return Collection<string, Collection<int, Bookmark>>
     */
    #[Computed]
    public function bookmarksByFolder(): Collection
    {
        if (($user = auth()->user()) === null) {
            return collect();
        }

        $bookmarks = Bookmark::whereBelongsTo($user)
            ->with('folder')
            ->orderBy('name')
            ->get();

        // Group by bookmark folder (or 'Uncategorized' if folder is null)
        $grouped = $bookmarks->groupBy(function (Bookmark $bookmark) {
            if ($bookmark->folder) {
                return json_encode(['id' => $bookmark->folder->id, 'name' => $bookmark->folder->name]);
            }

            // Fallback to the old folder field for backward compatibility
            return json_encode(['id' =>  null, 'name' => __('page-bookmarks::translation.uncategorized')]);
        });

        /** @var Collection<string, Collection<int, Bookmark>> $result */
        $result = collect();

        // Convert to the right Collection types for PHPStan
        foreach ($grouped as $folder => $items) {
            /** @var Collection<int, Bookmark> $bookmarkCollection */
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
        $bookmark = Bookmark::whereBelongsTo(auth()->user())->find($id);

        if ($bookmark) {
            $bookmark->delete();

            Notification::make()
                ->title(__('page-bookmarks::translation.bookmark_deleted_successfully'))
                ->success()
                ->send();

            $this->dispatch('refreshBookmarks');
        }
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

    public function bookmarkItemsAction(): Action
    {
        return Action::make('bookmark-items')
            ->modalHeading(__('page-bookmarks::translation.my_bookmarks'))
            ->slideOver(config('page-bookmarks.modal.view_bookmarks') === 'slideOver');
    }

    public function deleteFolderAction(): Action
    {
        return Action::make('deleteFolder')
            ->requiresConfirmation()
            ->modalHeading(__('page-bookmarks::translation.delete_folder'))
            ->modalDescription(__('page-bookmarks::translation.contained_bookmarks_will_be_moved_to_uncategorized'))
            ->color('danger')
            ->action(function (array $arguments, Action $action) {
                $folderId = $arguments['id'];

                $folder = BookmarkFolder::whereBelongsTo(auth()->user())->whereKey($folderId);

                if ($folder->delete()) {
                    $this->dispatch('refreshBookmarks');
                } else {
                    $action->failure();
                }
            })
            ->successNotificationTitle(__('page-bookmarks::translation.folder_deleted_successfully'));
    }

    public function render(): View
    {
        return view('page-bookmarks::livewire.bookmark-viewer');
    }
}
