<table class="meeting">
    <tr>
        <td class="time">
            {{ $meeting->time_formatted }}
        </td>
        <td>
            <div class="name">
                {{ $meeting->name }}
            </div>
            <div>
                @if ($meeting->location && $meeting->location !== $meeting->name)
                    {{ $meeting->location }},
                @endif
                {{ $meeting->address }}
            </div>
            @if (empty($region) || $region !== $meeting->regions_formatted)
                <div>
                    {{ $meeting->regions_formatted }}
                </div>
            @endif
        </td>
        <td class="types">
            {{ implode(', ', $meeting->types) }}
        </td>
    </tr>
</table>
