{{-- Figures of a distribution chart, for screen readers. --}}
<table>
    <thead><tr><th>{{ __('Grupo') }}</th><th>{{ __('Personas') }}</th><th>%</th></tr></thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr><td>{{ $fila['grupo'] }}</td><td>{{ $fila['total'] }}</td><td>{{ $fila['porcentaje'] }}</td></tr>
        @endforeach
    </tbody>
</table>
