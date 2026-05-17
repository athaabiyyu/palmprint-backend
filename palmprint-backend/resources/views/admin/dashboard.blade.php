@extends('layouts.admin')

@section('content')

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <small class="text-muted">
            {{ ucfirst($hari) }}, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}
        </small>
    </div>
    @if($semesterAktif)
        <span class="badge bg-success fs-6">
            <i class="bi bi-calendar-check me-1"></i>
            {{ $semesterAktif->nama }}
        </span>
    @else
        <span class="badge bg-warning text-dark fs-6">Tidak ada semester aktif</span>
    @endif
</div>

{{-- Row 1 — Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-people fs-3 text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Mahasiswa Aktif</div>
                    <div class="fw-bold fs-4">{{ $totalMahasiswa }}</div>
                    @if($belumPalmprint > 0)
                        <small class="text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            {{ $belumPalmprint }} belum palmprint
                        </small>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-person-badge fs-3 text-success"></i>
                </div>
                <div>
                    <div class="text-muted small">Dosen Aktif</div>
                    <div class="fw-bold fs-4">{{ $totalDosen }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-building fs-3 text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small">Kelas</div>
                    <div class="fw-bold fs-4">{{ $totalKelas }}</div>
                    <small class="text-muted">Semester ini</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-danger bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-book fs-3 text-danger"></i>
                </div>
                <div>
                    <div class="text-muted small">Mata Kuliah</div>
                    <div class="fw-bold fs-4">{{ $totalMatkul }}</div>
                    <small class="text-muted">Semester ini</small>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Row 2 — Jadwal Hari Ini + Sesi Aktif --}}
<div class="row g-3 mb-4">

    {{-- Jadwal Hari Ini --}}
    <div class="col-md-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-calendar-day me-2 text-primary"></i>
                    Jadwal Hari Ini
                    <span class="badge bg-primary ms-1">{{ $jadwalHariIni->count() }}</span>
                </h6>
            </div>
            <div class="card-body">
                @if($jadwalHariIni->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x fs-1"></i>
                        <p class="mt-2">Tidak ada jadwal hari ini</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Jam</th>
                                    <th>Mata Kuliah</th>
                                    <th>Kelas</th>
                                    <th>Dosen</th>
                                    <th>Sesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($jadwalHariIni as $j)
                                <tr>
                                    <td>
                                        <small>{{ substr($j->jam_mulai, 0, 5) }} - {{ substr($j->jam_selesai, 0, 5) }}</small>
                                    </td>
                                    <td>{{ $j->mataKuliah?->nama ?? '-' }}</td>
                                    <td><span class="badge bg-secondary">{{ $j->kelas?->nama ?? '-' }}</span></td>
                                    <td><small>{{ $j->dosen?->nama ?? '-' }}</small></td>
                                    <td>
                                        @if($j->sesiAktif)
                                            <span class="badge bg-success">
                                                <i class="bi bi-record-circle me-1"></i>Aktif
                                            </span>
                                        @else
                                            <span class="badge bg-light text-muted">Belum</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sesi Absensi Aktif --}}
    <div class="col-md-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-record-circle me-2 text-success"></i>
                    Sesi Absensi Aktif
                    <span class="badge bg-success ms-1">{{ $sesiAktif->count() }}</span>
                </h6>
            </div>
            <div class="card-body">
                @if($sesiAktif->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-slash-circle fs-1"></i>
                        <p class="mt-2">Tidak ada sesi aktif</p>
                    </div>
                @else
                    @foreach($sesiAktif as $sesi)
                    <div class="d-flex align-items-start gap-3 mb-3 p-3 bg-success bg-opacity-10 rounded-3">
                        <div class="bg-success bg-opacity-25 p-2 rounded-circle">
                            <i class="bi bi-person-check text-success"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">{{ $sesi->jadwal?->mataKuliah?->nama ?? '-' }}</div>
                            <small class="text-muted">
                                {{ $sesi->jadwal?->kelas?->nama ?? '-' }} •
                                {{ $sesi->jadwal?->dosen?->nama ?? '-' }}
                            </small>
                            <div>
                                <small class="text-success">
                                    <i class="bi bi-clock me-1"></i>
                                    Dibuka {{ \Carbon\Carbon::parse($sesi->dibuka_at)->format('H:i') }}
                                </small>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Row 3 — Grafik + Mahasiswa Belum Palmprint --}}
<div class="row g-3">

    {{-- Grafik Kehadiran --}}
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>
                    Kehadiran 7 Hari Terakhir
                </h6>
            </div>
            <div class="card-body">
                <canvas id="grafikKehadiran" height="100"></canvas>
            </div>
        </div>
    </div>

    {{-- Info Palmprint --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-3">
                <h6 class="fw-bold mb-0">
                    <i class="bi bi-hand-index me-2 text-warning"></i>
                    Status Palmprint
                </h6>
            </div>
            <div class="card-body d-flex flex-column justify-content-center">
                @php
                    $totalMhs      = $totalMahasiswa;
                    $sudahPalmprint = $totalMhs - $belumPalmprint;
                    $persen         = $totalMhs > 0
                        ? round(($sudahPalmprint / $totalMhs) * 100)
                        : 0;
                @endphp

                <div class="text-center mb-3">
                    <div class="fs-1 fw-bold text-success">{{ $persen }}%</div>
                    <div class="text-muted">Mahasiswa terdaftar palmprint</div>
                </div>

                <div class="progress mb-3" style="height: 10px">
                    <div class="progress-bar bg-success"
                        style="width: {{ $persen }}%"></div>
                </div>

                <div class="d-flex justify-content-between">
                    <div class="text-center">
                        <div class="fw-bold text-success fs-5">{{ $sudahPalmprint }}</div>
                        <small class="text-muted">Sudah</small>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold text-warning fs-5">{{ $belumPalmprint }}</div>
                        <small class="text-muted">Belum</small>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold fs-5">{{ $totalMhs }}</div>
                        <small class="text-muted">Total</small>
                    </div>
                </div>

                @if($belumPalmprint > 0)
                    <a href="/admin/mahasiswa?palmprint=belum"
                        class="btn btn-warning btn-sm mt-3 w-100">
                        <i class="bi bi-eye me-1"></i>Lihat Mahasiswa Belum Palmprint
                    </a>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = @json($grafik->pluck('label'));
const data   = @json($grafik->pluck('jumlah'));

new Chart(document.getElementById('grafikKehadiran'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label     : 'Jumlah Hadir',
            data,
            backgroundColor : 'rgba(13, 110, 253, 0.2)',
            borderColor     : 'rgba(13, 110, 253, 1)',
            borderWidth     : 2,
            borderRadius    : 6,
        }]
    },
    options: {
        responsive : true,
        plugins    : { legend: { display: false } },
        scales     : {
            y: {
                beginAtZero : true,
                ticks       : { stepSize: 1 },
            }
        }
    }
});
</script>
@endpush