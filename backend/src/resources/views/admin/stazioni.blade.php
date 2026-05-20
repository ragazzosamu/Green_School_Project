@extends('admin.layout')
@section('page-title', 'Stazioni')

@section('content')

<div style="margin-bottom:1.25rem;">
    <h2 style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);">Stazioni di ricarica</h2>
    <p style="font-size:0.78rem;color:var(--text-3);margin-top:2px;">{{ $stazioni->count() }} stazioni nel sistema</p>
</div>

@if(session('success'))
    <div class="alert-success">{{ session('success') }}</div>
@endif

<div class="admin-card">
    <table class="admin-table">
        <thead><tr>
            <th>Stazione</th>
            <th>Indirizzo</th>
            <th style="text-align:center;">Punti totali</th>
            <th style="text-align:center;">Punti liberi</th>
            <th style="text-align:center;">Punti online</th>
            <th>Stato</th>
            <th>Azione</th>
        </tr></thead>
        <tbody>
        @forelse($stazioni as $s)
        <tr>
            <td>
                <p style="font-weight:600;color:var(--text);">{{ $s->nome ?? '— da configurare —' }}</p>
                <p style="font-size:0.68rem;color:var(--text-3);font-family:monospace;">MAC {{ $s->id_stazione }}</p>
                @if($s->stato_setup === 'in_setup')
                    <span class="badge-pill badge-yellow" style="margin-top:4px;">In setup</span>
                @endif
            </td>
            <td style="font-size:0.78rem;color:var(--text-2);">{{ $s->indirizzo ?? '—' }}</td>
            <td style="text-align:center;">{{ $s->punti_totali }}</td>
            <td style="text-align:center;">
                <span class="badge-pill {{ $s->punti_liberi > 0 ? 'badge-green' : 'badge-red' }}">
                    {{ $s->punti_liberi }}
                </span>
            </td>
            <td style="text-align:center;">{{ $s->punti_online }}</td>
            <td>
                {{-- Tag online/offline derivato a runtime: stato_hardware su
                     stazioni non esiste piu'. La stazione e' online se non e'
                     in manutenzione e ha almeno 1 punto online. --}}
                @if($s->in_manutenzione)
                    <span class="badge-pill badge-yellow">manutenzione</span>
                @elseif($s->online ?? false)
                    <span class="badge-pill badge-green">online</span>
                @else
                    <span class="badge-pill badge-red">offline</span>
                @endif
            </td>
            <td>
                @if($s->stato_setup === 'in_setup')
                    <a href="/admin/stazioni/{{ rawurlencode($s->id_stazione) }}/setup" class="btn btn-primary btn-sm">Completa setup</a>
                @else
                    <form method="POST" action="/admin/stazioni/{{ rawurlencode($s->id_stazione) }}/toggle" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $s->in_manutenzione ? 'btn-outline' : 'btn-danger' }}">
                            {{ $s->in_manutenzione ? 'Riporta online' : 'Manutenzione' }}
                        </button>
                    </form>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:2rem;">Nessuna stazione trovata.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection
