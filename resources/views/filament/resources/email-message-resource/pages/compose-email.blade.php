<x-filament-panels::page>
    <form wire:submit="sendEmail">
        {{ $this->form }}
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:24px;">
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\EmailMessageResource::getUrl('index')">Cancel</x-filament::button>
            <x-filament::button type="button" color="gray" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">Save draft</x-filament::button>
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled" wire:target="sendEmail">Send Email</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
