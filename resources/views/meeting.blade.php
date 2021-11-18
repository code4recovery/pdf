<div class="meeting">
    <div class="time">
        {{ $meeting->time_formatted }}
    </div>
    <div>
        <div class="name">
            {{ $meeting->name }}
        </div>
        <div>
            {{ $meeting->location }}
        </div>
        <div>
            {{ $meeting->address }}
        </div>
        <div>
            {{ $meeting->regions_formatted }}
        </div>
    </div>
    <div class="types">
        {{ implode(', ', $meeting->types) }}
    </div>
</div>
