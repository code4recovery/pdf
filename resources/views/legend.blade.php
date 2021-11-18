<div class="legend">
    <h1>Meeting Types</h1>
    @foreach ($types_in_use as $type)
        <div class="type-row">
            <span class="type">{{ $type }}</span>
            <span>{{ $types[$type] }}</span>
        </div>
    @endforeach
</div>
