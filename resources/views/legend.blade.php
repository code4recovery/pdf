<div class="legend">
    @if ($legend_header)
        <div class="legend-header">{{ $legend_header }}</div>
    @endif
    <h1>Meeting Types</h1>
    @foreach ($types_in_use as $type)
        <div class="type-row">
            <span class="type">{{ $type }}</span>
            <span>{{ $types[$type] }}</span>
        </div>
    @endforeach
    @if ($legend_footer)
        <div class="legend-footer">{{ $legend_footer }}</div>
    @endif
</div>
