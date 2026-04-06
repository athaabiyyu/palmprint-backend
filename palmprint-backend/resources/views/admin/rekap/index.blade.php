@extends('layouts.admin')

@section('content')
<h4 class="fw-bold mb-4">Rekap Absensi</h4>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Kelas</label>
                <select id="filterKelas" class="form-select" onchange="loadJadwal()">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach($kelas as $k)
                        <option value="{{ $k->id }}">{{ $k->nama }} ({{ $k->semester->nama ?? '-' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Mata Kuliah</label>
                <select id="filterJadwal" class="form-select" disabled>
                    <option value="">-- Pilih Mata Kuliah --</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Dari Tanggal</label>
                <input type="date" id="tanggalDari" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Sampai Tanggal</label>
                <input type="date" id="tanggalSampai" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="loadRekap()">
                    <i class="bi bi-search me-1"></i> Tampilkan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hasil Rekap -->
<div id="hasilRekap" class="d-none">

    <!-- Info Jadwal -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="text-muted small">Mata Kuliah</div>
                    <div class="fw-bold" id="infoMatkul">-</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Kelas</div>
                    <div class="fw-bold" id="infoKelas">-</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Total Pertemuan</div>
                    <div class="fw-bold" id="infoPertemuan">-</div>
                </div>
                <div class="col-md-3 d-flex align-items-center gap-2">
                    <button class="btn btn-success btn-sm" onclick="exportExcel()">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="exportPdf()">
                        <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Rekap -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tableRekap">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama</th>
                            <th class="text-center text-success">Hadir</th>
                            <th class="text-center text-danger">Alpha</th>
                            <th class="text-center text-warning">Izin</th>
                            <th class="text-center text-info">Sakit</th>
                            <th class="text-center">% Hadir</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="bodyRekap"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Empty State -->
<div id="emptyState" class="text-center text-muted py-5">
    <i class="bi bi-clipboard-data fs-1 d-block mb-2"></i>
    Pilih kelas dan mata kuliah untuk melihat rekap absensi
</div>

@endsection

@push('scripts')
<script>
let rekapData = null;

// ── Load Jadwal by Kelas ──
async function loadJadwal() {
    const kelasId = document.getElementById('filterKelas').value;
    const select  = document.getElementById('filterJadwal');

    if (!kelasId) {
        select.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
        select.disabled  = true;
        return;
    }

    const res = await axios.get(`/api/admin/rekap/jadwal/${kelasId}`);
    select.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
    res.data.forEach(j => {
        select.innerHTML += `<option value="${j.id}">${j.mata_kuliah.nama} (${j.hari} ${j.jam_mulai})</option>`;
    });
    select.disabled = false;
}

// ── Load Rekap ──
async function loadRekap() {
    const jadwalId      = document.getElementById('filterJadwal').value;
    const tanggalDari   = document.getElementById('tanggalDari').value;
    const tanggalSampai = document.getElementById('tanggalSampai').value;

    if (!jadwalId) {
        alert('Pilih mata kuliah terlebih dahulu!');
        return;
    }

    let url = `/api/admin/rekap?jadwal_id=${jadwalId}`;
    if (tanggalDari)   url += `&tanggal_dari=${tanggalDari}`;
    if (tanggalSampai) url += `&tanggal_sampai=${tanggalSampai}`;

    const res  = await axios.get(url);
    rekapData  = res.data;

    // Update info
    document.getElementById('infoMatkul')    .innerText = rekapData.jadwal.mata_kuliah.nama;
    document.getElementById('infoKelas')     .innerText = rekapData.jadwal.kelas.nama;
    document.getElementById('infoPertemuan') .innerText = rekapData.sesis.length + ' pertemuan';

    // Render tabel
    const tbody = document.getElementById('bodyRekap');
    if (rekapData.rekap.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Belum ada data absensi</td></tr>';
    } else {
        tbody.innerHTML = rekapData.rekap.map((m, i) => {
            const persen  = m.persentase;
            const warna   = persen >= 75 ? 'success' : persen >= 50 ? 'warning' : 'danger';
            const status  = persen >= 75 ? 'Lulus' : 'Tidak Lulus';
            const statusColor = persen >= 75 ? 'success' : 'danger';

            return `
                <tr>
                    <td>${i + 1}</td>
                    <td>${m.nim}</td>
                    <td>${m.nama}</td>
                    <td class="text-center"><span class="badge bg-success">${m.hadir}</span></td>
                    <td class="text-center"><span class="badge bg-danger">${m.alpha}</span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark">${m.izin}</span></td>
                    <td class="text-center"><span class="badge bg-info text-dark">${m.sakit}</span></td>
                    <td class="text-center">
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-${warna}" style="width: ${persen}%">
                                ${persen}%
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-${statusColor}">${status}</span></td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('hasilRekap').classList.remove('d-none');
    document.getElementById('emptyState').classList.add('d-none');
}

// ── Export Excel ──
async function exportExcel() {
    const jadwalId      = document.getElementById('filterJadwal').value;
    const tanggalDari   = document.getElementById('tanggalDari').value;
    const tanggalSampai = document.getElementById('tanggalSampai').value;

    let url = `/admin/rekap/export-excel?jadwal_id=${jadwalId}`;
    if (tanggalDari)   url += `&tanggal_dari=${tanggalDari}`;
    if (tanggalSampai) url += `&tanggal_sampai=${tanggalSampai}`;

    window.open(url, '_blank');
}

// ── Export PDF ──
async function exportPdf() {
    const jadwalId      = document.getElementById('filterJadwal').value;
    const tanggalDari   = document.getElementById('tanggalDari').value;
    const tanggalSampai = document.getElementById('tanggalSampai').value;

    let url = `/admin/rekap/export-pdf?jadwal_id=${jadwalId}`;
    if (tanggalDari)   url += `&tanggal_dari=${tanggalDari}`;
    if (tanggalSampai) url += `&tanggal_sampai=${tanggalSampai}`;

    window.open(url, '_blank');
}
</script>
@endpush