@if ($errors->any())
    <div {{ $attributes->class(['rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900']) }} role="alert">
        <p class="font-semibold">{{ __('Provjerite označena polja prije spremanja.') }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
