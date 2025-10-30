<?php

declare(strict_types=1);

namespace JaysonTemporas\PageBookmarks\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JaysonTemporas\PageBookmarks\Models\Bookmark;
use JaysonTemporas\PageBookmarks\Models\BookmarkFolder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property Schema $form
 */
#[On('refreshBookmarks')]
class BookmarkManager extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    /**
     * Get available bookmark folders for the current user
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableBookmarkFolders(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        /** @var array<int, string> $folders */
        $folders = BookmarkFolder::query()->whereBelongsTo($user)
            ->pluck('name', 'id')
            ->toArray();

        return $folders;
    }

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

        $bookmarks = Bookmark::whereBelongsTo($user)
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
                ->duration(1200)
                ->title(__('page-bookmarks::translation.bookmark_deleted_successfully'))
                ->success()
                ->send();
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

    public function render(): View
    {
        return view('page-bookmarks::livewire.bookmark-manager');
    }

    public function addBookmarkAction(): Action
    {
        return Action::make('add-bookmark')
            ->modalHeading(__('page-bookmarks::translation.add_bookmark'))
            ->slideOver(config('page-bookmarks.modal.add_bookmark') === 'slideOver')
            ->model(Bookmark::class)
            ->modalWidth(Width::Small)
            ->fillForm(fn (array $arguments): array => [
                'name' => $arguments['name'],
                'url' => $arguments['url'],
            ])
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('bookmark_folder_id')
                    ->label(__('page-bookmarks::translation.folder'))
                    ->options(BookmarkFolder::whereBelongsTo(auth()->user())->pluck('name', 'id'))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data) {
                        return auth()->user()?->bookmarkFolders()
                            ->create($data)
                            ->getKey();
                    })
                    ->createOptionAction(
                        fn (Action $action) => $action
                            ->modalWidth(Width::Small)
                            ->after(fn() => $this->dispatch('refreshBookmarks')),
                    )
                    ->nullable(),

                TextInput::make('url')
                    ->label('URL')
                    ->disabled()
                    ->dehydrated(),
            ])
            ->modalSubmitActionLabel(__('page-bookmarks::translation.add'))
            ->action(function (array $data, Action $action) {
                if (($user = auth()->user()) === null) {
                    return;
                }

                $name = Arr::get($data, 'name', '');
                $url = Arr::get($data, 'url') ?? request()->url();

                $bookmarkFolderId = Arr::get($data, 'bookmark_folder_id');

                // Check for existing bookmarks with the same name or URL for this user
                $existingBookmark = Bookmark::whereBelongsTo($user)
                    ->where(function ($query) use ($name, $url): void {
                        $query->where('name', $name)
                            ->orWhere('url', $url);
                    })
                    ->first();

                if (! is_null($existingBookmark)) {
                    // Determine if it's a duplicate name, URL, or both
                    if ($existingBookmark->name === $name && $existingBookmark->url === $url) {
                        $duplicateField = __('page-bookmarks::translation.bookmark_with_this_name_and_url');
                    } elseif ($existingBookmark->name === $name) {
                        $duplicateField = __('page-bookmarks::translation.bookmark_with_this_name');
                    } else {
                        $duplicateField = __('page-bookmarks::translation.bookmark_for_this_url');
                    }

                    Notification::make()
                        ->title(__('page-bookmarks::translation.you_already_have_a_duplicate', [
                            'duplicate' => $duplicateField,
                        ]))
                        ->warning()
                        ->send();

                    $action->halt();
                }

                // Create and save the new bookmark
                $bookmark = new Bookmark;
                $bookmark->fill([
                    'user_id' => $user->id,
                    'name' => $name,
                    'url' => $url,
                    'bookmark_folder_id' => $bookmarkFolderId,
                ])->save();

                $bookmark->save();

                $this->dispatch('refreshBookmarks');
            })
            ->successNotificationTitle(__('page-bookmarks::translation.bookmark_added_successfully'));
    }
}
