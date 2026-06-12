<div class="bold">{{ $heading }}</div>
<table class="grid" style="margin-top:3px;">
    <tbody>
        @forelse($data as $label => $count)
            <tr><td>{{ $label }}</td><td class="center" style="width:34px;">{{ $count }}</td></tr>
        @empty
            <tr><td class="muted">No data</td><td></td></tr>
        @endforelse
    </tbody>
</table>
