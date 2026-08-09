<x-filament-widgets::widget>
    <x-filament::section heading="Quick Actions">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($actions as $action)
                <a
                    href="{{ $action['url'] }}"
                    class="rounded-lg border border-gray-200 px-4 py-3 text-sm transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                >
                    <div class="flex items-start gap-3">
                        {{ \Filament\Support\generate_icon_html(
                            $action['icon'],
                            attributes: (new \Illuminate\View\ComponentAttributeBag)
                                ->class('mt-0.5 h-5 w-5 text-primary-600 dark:text-primary-400')
                        ) }}

                        <div class="min-w-0">
                            <div class="font-medium text-gray-950 dark:text-white">
                                {{ $action['label'] }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $action['description'] }}
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
