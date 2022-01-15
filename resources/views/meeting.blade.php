<div class="meeting">
    <div class="time">
        {{ $meeting->time_formatted }}
    </div>
    <div>
        <div class="name">
            {{ $meeting->name }}
        </div>
        <div>
            @if ($meeting->name !== $meeting->location)
                {{ $meeting->location }},
            @endif
            {{ $meeting->address }}
        </div>
        @if (empty($region) || $region !== $meeting->regions_formatted)
            <div>
                {{ $meeting->regions_formatted }}
            </div>
        @endif
    </div>
    <div class="types">
        {{ implode(', ', $meeting->types) }}
    </div>
</div>
