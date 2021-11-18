<div class="meeting">
    <div class="time">
        {{ $meeting->time_formatted }}
    </div>
    <div>
        <div class="name">
            {{ $meeting->name }}
        </div>
        @if ($meeting->name !== $meeting->location)
            <div>
                {{ $meeting->location }}
            </div>
        @endif
        <div>
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
