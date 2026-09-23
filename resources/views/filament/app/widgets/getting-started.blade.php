{{-- Gaya ditulis inline karena CSS Filament yang sudah dikompilasi tidak memuat kelas utilitas buatan sendiri. --}}
<x-filament::section icon="heroicon-o-flag" icon-color="primary">
    <x-slot name="heading">Mulai dari sini</x-slot>
    <x-slot name="description">
        Tinggal {{ $this->getRemainingCount() }} langkah lagi sebelum buku ini siap dipakai sehari-hari.
    </x-slot>

    <ol style="display: flex; flex-direction: column; gap: 0.75rem;">
        @foreach ($this->getSteps() as $step)
            <li style="display: flex; align-items: flex-start; gap: 0.75rem;">
                <span style="flex-shrink: 0; margin-top: 0.125rem;">
                    @if ($step['isDone'])
                        <x-filament::icon icon="heroicon-s-check-circle" style="width: 1.25rem; height: 1.25rem; color: rgb(var(--primary-600));" />
                    @else
                        <x-filament::icon icon="heroicon-o-clock" style="width: 1.25rem; height: 1.25rem; color: rgb(var(--gray-400));" />
                    @endif
                </span>

                <div style="min-width: 0; flex: 1;">
                    <p @style(['font-weight: 500', 'text-decoration: line-through; opacity: 0.6' => $step['isDone']])>
                        {{ $step['title'] }}
                    </p>

                    @unless ($step['isDone'])
                        <p style="margin-top: 0.125rem; font-size: 0.875rem; color: rgb(var(--gray-500));">
                            {{ $step['body'] }}
                        </p>
                    @endunless
                </div>

                @unless ($step['isDone'])
                    <x-filament::link :href="$step['url']" style="flex-shrink: 0;">
                        Kerjakan
                    </x-filament::link>
                @endunless
            </li>
        @endforeach
    </ol>
</x-filament::section>
