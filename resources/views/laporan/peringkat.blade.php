<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Peringkat biller {{ $periode->kode }}</title>
    <style>
        @page { margin: 22px 26px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #0F1B2A; }
        .kepala { width: 100%; border-bottom: 2px solid #0A5C8F; padding-bottom: 8px; margin-bottom: 12px; }
        .kepala td { vertical-align: middle; }
        .judul { font-size: 15px; font-weight: bold; margin: 0; }
        .sub { color: #546474; margin: 2px 0 0; }
        .ringkas { width: 100%; margin-bottom: 12px; border-collapse: separate; border-spacing: 6px 0; }
        .ringkas td { border: 1px solid #DCE3EA; border-radius: 6px; padding: 6px 8px; width: 25%; }
        .ringkas .label { color: #546474; font-size: 8.5px; }
        .ringkas .angka { font-size: 14px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #F3F6F9; color: #546474; font-weight: normal; text-align: left; padding: 5px 6px; border-bottom: 1px solid #DCE3EA; }
        table.data td { padding: 5px 6px; border-bottom: 1px solid #EDF1F5; }
        .kanan { text-align: right; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .juara { background: #FFF6CC; }
        .chip { border-radius: 8px; padding: 1px 6px; font-size: 8px; }
        .pembinaan { background: #FCEAEA; color: #A61F17; }
        .dipantau { background: #FFF6CC; color: #6B5000; }
        .naik { color: #13703F; }
        .turun { color: #A61F17; }
        .kaki { margin-top: 12px; color: #546474; font-size: 8.5px; }
    </style>
</head>
<body>
    <table class="kepala">
        <tr>
            <td style="width: 90px;"><img src="{{ public_path('images/logo-pln.png') }}" style="height: 30px;" alt="PLN"></td>
            <td>
                <p class="judul">Laporan peringkat biller · {{ $periode->nama() }}</p>
                <p class="sub">
                    {{ $unit }} · Periode {{ $periode->kode }} · {{ $final ? 'Skor final' : 'Skor sementara (periode masih berjalan)' }}
                </p>
            </td>
        </tr>
    </table>

    @php($fmt = fn ($n, $d = 1) => number_format((float) $n, $d, ',', '.'))
    <table class="ringkas">
        <tr>
            <td><div class="label">Pelanggan tercatat (dikirim / target)</div><div class="angka">{{ $fmt($ringkasan['terkirim'], 0) }} / {{ $fmt($ringkasan['target'], 0) }}</div></td>
            <td><div class="label">Disetujui</div><div class="angka">{{ $fmt($ringkasan['disetujui'], 0) }}</div></td>
            <td><div class="label">Akurasi tim</div><div class="angka">{{ $ringkasan['akurasi_tim'] !== null ? $fmt($ringkasan['akurasi_tim']).'%' : '–' }}</div></td>
            <td><div class="label">Anomali ditandai sistem</div><div class="angka">{{ $fmt($ringkasan['anomali'], 0) }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Biller</th>
                <th>Unit / rute</th>
                <th class="kanan">Tercatat / target</th>
                <th class="kanan">Akurasi</th>
                <th class="kanan">Penyelesaian</th>
                <th class="kanan">Tepat waktu</th>
                <th class="kanan">Bukti lengkap</th>
                <th class="kanan">Skor</th>
                <th class="kanan">Tren</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($baris as $b)
                <tr class="{{ $b['peringkat'] <= 3 ? 'juara' : '' }}">
                    <td>{{ $b['peringkat'] }}</td>
                    <td>{{ $b['nama'] }}<br><span class="mono" style="color:#546474">{{ $b['nip'] }}</span></td>
                    <td>{{ $b['unit'] }}<br><span style="color:#546474">{{ collect($b['rute'])->pluck('kode')->join(', ') }}</span></td>
                    <td class="kanan">{{ $b['tercatat'] }} / {{ $b['target'] }}</td>
                    <td class="kanan">{{ $fmt($b['akurasi']) }}%</td>
                    <td class="kanan">{{ $fmt($b['penyelesaian']) }}%</td>
                    <td class="kanan">{{ $fmt($b['ketepatan_waktu']) }}%</td>
                    <td class="kanan">{{ $fmt($b['bukti_lengkap']) }}%</td>
                    <td class="kanan mono"><strong>{{ $fmt($b['skor'], 2) }}</strong></td>
                    <td class="kanan {{ ($b['tren'] ?? 0) >= 0 ? 'naik' : 'turun' }}">
                        {{ $b['tren'] === null ? '–' : (($b['tren'] >= 0 ? '+' : '').$fmt($b['tren'], 2)) }}
                    </td>
                    <td>
                        @if ($b['status'] === 'pembinaan')
                            <span class="chip pembinaan">Perlu pembinaan</span>
                        @elseif ($b['status'] === 'dipantau')
                            <span class="chip dipantau">Dipantau</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="kaki">
        Skor = {{ $fmt($bobot['akurasi'] / 100, 2) }}·Akurasi + {{ $fmt($bobot['penyelesaian'] / 100, 2) }}·Penyelesaian
        + {{ $fmt($bobot['tepat_waktu'] / 100, 2) }}·Tepat waktu + {{ $fmt($bobot['bukti_lengkap'] / 100, 2) }}·Bukti lengkap.
        Hanya catatan yang disetujui yang masuk skor. Seri diurutkan dari akurasi tertinggi.
        Dicetak {{ $dicetak->locale('id')->translatedFormat('j F Y, H.i') }} WIB oleh {{ $oleh }}.
    </p>
</body>
</html>
