{{-- Metric tiles with hints: a copy of platform::layouts.metric plus an explanation for each metric --}}
<div class="mb-3">
    @isset($title)
        <legend class="text-black px-4 mb-0">
            {{ __($title) }}
        </legend>
    @endisset
    <div class="row mb-2 g-3 g-mb-4">
        @foreach($metrics as $key => $metric)
            <div class="col">
                <div class="p-4 bg-white rounded shadow-sm h-100 d-flex flex-column">
                    <small class="text-muted d-block mb-1">
                        {{ __($key) }}
                        <x-orchid-popover :content="$hints[$key] ?? null"/>
                    </small>
                    <p class="h3 text-black fw-light mt-auto mb-0">
                        {{ is_array($metric) ? $metric['value'] : $metric }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>
