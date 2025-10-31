<div class="flex justify-end">
    <x-filament::icon-button color="gray"
        icon="{{ $this->getIcons()['add_bookmark'] }}"

        x-data="{
            addBookmark() {
                const h1 = document.querySelector('h1');
                const pageTitle = h1 ? h1.textContent.trim() : document.title;

                $wire.mountAction('addBookmark', {name: pageTitle, url: window.location.href})
            }
        }"

        x-on:click="addBookmark"
        x-on:keydown.meta.shift.b.prevent.document="addBookmark"
    />

    <x-filament-actions::modals />
</div>
