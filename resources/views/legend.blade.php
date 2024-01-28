<div class="legend">
    <span class="heading">{{ $meeting_types_heading }}</span>
    @foreach ($types_in_use as $type)
        <div class="type-row">
            <span class="type">{{ $type }}</span>
            <span>{{ $types[$type] }}</span>
        </div>
    @endforeach
</div>
