<div class="flex justify-end">
    <!-- Bookmark Icon Button -->
    <x-filament::icon-button
        icon="{{ $this->getIcons()['add_bookmark'] }}"
        class="text-gray-500 transition-colors hover:text-primary-500"
        x-on:click="$dispatch('open-modal', { id: 'bookmark-form-modal' }); $nextTick(() => {
            // Try to get the title from h1 tag
            const h1 = document.querySelector('h1');
            const pageTitle = h1 ? h1.textContent.trim() : document.title;

            // Dispatch event to Livewire to set the title
            $wire.setBookmarkName(pageTitle);
        })"
        x-on:keydown.meta.shift.b.prevent.document="$dispatch('open-modal', { id: 'bookmark-form-modal' }); $nextTick(() => {
            // Try to get the title from h1 tag
            const h1 = document.querySelector('h1');
            const pageTitle = h1 ? h1.textContent.trim() : document.title;

            // Dispatch event to Livewire to set the title
            $wire.setBookmarkName(pageTitle);
        })"
    />

    <x-filament::modal
        id="bookmark-form-modal"
        width="md"
        :slide-over="config('page-bookmarks.modal.add_bookmark') === 'slideOver' ? true : false"
        :heading="__('page-bookmarks::translation.add_bookmark')"
    >
        <form wire:submit.prevent="save">
            {{ $this->form }}

            <div class="flex justify-end mt-6 gap-x-2">
                <x-filament::button type="submit">
                    Save
                </x-filament::button>
            </div>
        </form>
    </x-filament::modal>

    <x-filament-actions::modals />
</div>
