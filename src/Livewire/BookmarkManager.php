<?php

declare(strict_types=1);

namespace JaysonTemporas\PageBookmarks\Livewire;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use JaysonTemporas\PageBookmarks\Models\Bookmark;
use JaysonTemporas\PageBookmarks\Models\BookmarkFolder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property Schema $form
 */
class BookmarkManager extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        // Get current URL from the request
        $currentUrl = request()->url();

        // Initialize data with the current URL
        $this->data = [
            'url' => $currentUrl,
            'display_url' => $currentUrl,
        ];
    }

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
        $folders = BookmarkFolder::query()->where('user_id', $user->id)
            ->pluck('name', 'id')
            ->toArray();

        return $folders;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Hidden::make('url'),

                Select::make('bookmark_folder_id')
                    ->label(__('page-bookmarks::translation.folder'))
                    ->options(BookmarkFolder::query()->where('user_id', auth()->id())->pluck('name', 'id'))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->createOptionUsing(function (array $data, Set $set) {
                        $user = auth()->user();

                        if ($user === null) {
                            return null;
                        }

                        return $user->bookmarkFolders()->create($data)->getKey();
                    })
                    ->nullable(),

                TextInput::make('display_url')
                    ->label('URL')
                    ->disabled()
                    ->dehydrated(false),
            ])
            ->model(Bookmark::class)
            ->statePath('data');
    }

    /**
     * Set the bookmark name from JavaScript
     */
    public function setBookmarkName(string $name): void
    {
        $this->data['name'] = $name;
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
                ->duration(1200)
                ->title(__('page-bookmarks::translation.bookmark_deleted_successfully'))
                ->success()
                ->send();
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
        return view('page-bookmarks::livewire.bookmark-manager');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        // Extract and sanitize input values
        $name = isset($data['name']) && is_string($data['name']) ? $data['name'] : '';
        $url = isset($data['url']) && is_string($data['url']) ? $data['url'] : request()->url();
        $bookmarkFolderId = isset($data['bookmark_folder_id']) && is_numeric($data['bookmark_folder_id'])
            ? (int) $data['bookmark_folder_id']
            : null;

        // Check for existing bookmarks with the same name or URL for this user
        $existingBookmark = Bookmark::query()->where('user_id', $user->id)
            ->where(function ($query) use ($name, $url): void {
                $query->where('name', $name)
                    ->orWhere('url', $url);
            })
            ->first();

        if ($existingBookmark) {
            // Determine if it's a duplicate name, URL, or both
            $duplicateField = '';
            if ($existingBookmark->name === $name && $existingBookmark->url === $url) {
                $duplicateField = __('page-bookmarks::translation.bookmark_with_this_name_and_url');
            } elseif ($existingBookmark->name === $name) {
                $duplicateField = __('page-bookmarks::translation.bookmark_with_this_name');
            } else {
                $duplicateField = __('page-bookmarks::translation.bookmark_for_this_url');
            }

            Notification::make()
                ->title(__('page-bookmarks::translation.you_already_have_a_duplicate', ['duplicate' => $duplicateField]))
                ->warning()
                ->send();

            return;
        }

        // Create and save the new bookmark
        $bookmark = new Bookmark;
        $bookmark->user_id = $user->id;
        $bookmark->name = $name;
        $bookmark->url = $url;
        $bookmark->bookmark_folder_id = $bookmarkFolderId;
        $bookmark->save();

        $this->form->fill([
            'url' => request()->url(),
        ]);

        $this->dispatch('close-modal', id: 'bookmark-form-modal');
        $this->dispatch('refreshBookmarks');

        Notification::make()
            ->title(__('page-bookmarks::translation.bookmark_saved_successfully'))
            ->success()
            ->send();
    }
}
