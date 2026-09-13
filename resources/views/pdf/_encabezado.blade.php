<div class="encabezado">
    <table>
        <tr>
            <td>
                <div class="clinica">{{ $clinica->nombre }}</div>
                <div class="meta">
                    @if ($clinica->direccion){{ $clinica->direccion }}<br>@endif
                    @if ($clinica->telefono)Tel. {{ $clinica->telefono }} @endif
                    @if ($clinica->email) · {{ $clinica->email }}@endif
                    @if ($clinica->nit)<br>NIT/RUC: {{ $clinica->nit }}@endif
                </div>
            </td>
            <td class="der meta">
                Emitido: {{ now()->format('d/m/Y H:i') }}<br>
                {{ $clinica->web }}
            </td>
        </tr>
    </table>
</div>
