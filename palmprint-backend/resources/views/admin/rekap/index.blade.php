@extends('layouts.admin')

@section('content')
<h4 class="fw-bold mb-4">Rekap Absensi</h4>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">

            <div class="col-md-2">
                <label class="form-label fw-semibold">Semester</label>
                <select id="filterSemester" class="form-select" onchange="onSemesterChange()">
                    <option value="">-- Pilih Semester --</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Prodi</label>
                <select id="filterProdi" class="form-select" onchange="onProdiChange()" disabled>
                    <option value="">-- Pilih Prodi --</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Kelas</label>
                <select id="filterKelas" class="form-select" onchange="onKelasChange()" disabled>
                    <option value="">-- Pilih Kelas --</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Mata Kuliah</label>
                <select id="filterJadwal" class="form-select" disabled>
                    <option value="">-- Pilih Mata Kuliah --</option>
                </select>
            </div>

            <div class="col-md-1">
                <label class="form-label fw-semibold">Dari</label>
                <input type="date" id="tanggalDari" class="form-control">
            </div>

            <div class="col-md-1">
                <label class="form-label fw-semibold">Sampai</label>
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

    <!-- Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <div class="text-muted small">Prodi</div>
                    <div class="fw-bold" id="infoProdi">-</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Kelas</div>
                    <div class="fw-bold" id="infoKelas">-</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Mata Kuliah</div>
                    <div class="fw-bold" id="infoMatkul">-</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Dosen</div>
                    <div class="fw-bold" id="infoDosen">-</div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted small">Total Pertemuan</div>
                    <div class="fw-bold" id="infoPertemuan">-</div>
                </div>
                <div class="col-md-2 d-flex gap-2">
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

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
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
    Pilih semester, prodi, kelas, dan mata kuliah untuk melihat rekap
</div>
@endsection

@push('scripts')
<script>
let rekapData    = null;
let semesterAktifId = null;

// ── Init ──
async function init() {
    const [resSemester, resProdi] = await Promise.all([
        axios.get('/api/admin/semesters'),
        axios.get('/api/admin/prodis'),
    ]);

    // Semester
    const filterSemester = document.getElementById('filterSemester');
    filterSemester.innerHTML = '<option value="">-- Pilih Semester --</option>';
    resSemester.data.forEach(s => {
        if (s.is_active) semesterAktifId = String(s.id);
        const label = s.nama + (s.is_active ? ' ✓' : '');
        filterSemester.innerHTML += `<option value="${s.id}">${label}</option>`;
    });
    if (semesterAktifId) filterSemester.value = semesterAktifId;

    // Prodi
    const filterProdi = document.getElementById('filterProdi');
    filterProdi.innerHTML = '<option value="">-- Pilih Prodi --</option>';
    resProdi.data.forEach(p => {
        filterProdi.innerHTML += `<option value="${p.id}">${p.kode} - ${p.nama}</option>`;
    });
    filterProdi.disabled = false;
}

// ── Semester Change ──
function onSemesterChange() {
    // Reset ke bawah
    resetFrom('prodi');
}

// ── Prodi Change ──
async function onProdiChange() {
    const prodiId    = document.getElementById('filterProdi').value;
    const semesterId = document.getElementById('filterSemester').value;

    resetFrom('kelas');

    if (!prodiId) return;

    const params = new URLSearchParams();
    if (prodiId)    params.append('prodi_id', prodiId);
    if (semesterId) params.append('semester_id', semesterId);

    const res = await axios.get(`/api/admin/rekap/kelas?${params.toString()}`);

    const filterKelas = document.getElementById('filterKelas');
    filterKelas.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    res.data.forEach(k => {
        filterKelas.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
    });
    filterKelas.disabled = false;
}

// ── Kelas Change ──
async function onKelasChange() {
    const kelasId = document.getElementById('filterKelas').value;

    resetFrom('jadwal');

    if (!kelasId) return;

    const res = await axios.get(`/api/admin/rekap/jadwal/${kelasId}`);

    const filterJadwal = document.getElementById('filterJadwal');
    filterJadwal.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
    res.data.forEach(j => {
        const hariMap = { senin:'Sen', selasa:'Sel', rabu:'Rab', kamis:'Kam', jumat:'Jum' };
        filterJadwal.innerHTML +=
            `<option value="${j.id}">${j.mata_kuliah.nama} (${hariMap[j.hari]} ${j.jam_mulai.substring(0,5)})</option>`;
    });
    filterJadwal.disabled = false;
}

// ── Reset dropdown dari level tertentu ──
function resetFrom(level) {
    const levels = ['prodi', 'kelas', 'jadwal'];
    const start  = levels.indexOf(level);

    const configs = {
        prodi  : { id: 'filterProdi',  text: '-- Pilih Prodi --' },
        kelas  : { id: 'filterKelas',  text: '-- Pilih Kelas --' },
        jadwal : { id: 'filterJadwal', text: '-- Pilih Mata Kuliah --' },
    };

    levels.slice(start).forEach(l => {
        const el = document.getElementById(configs[l].id);
        el.innerHTML = `<option value="">${configs[l].text}</option>`;
        el.disabled  = (l !== level || l === 'prodi') ? true : false;
    });

    // Sembunyikan hasil rekap
    document.getElementById('hasilRekap').classList.add('d-none');
    document.getElementById('emptyState').classList.remove('d-none');
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

    const res = await axios.get(url);
    rekapData = res.data;

    // Update info
    document.getElementById('infoProdi')     .innerText = rekapData.jadwal.kelas?.prodi?.nama ?? '-';
    document.getElementById('infoKelas')     .innerText = rekapData.jadwal.kelas?.nama ?? '-';
    document.getElementById('infoMatkul')    .innerText = rekapData.jadwal.mata_kuliah?.nama ?? '-';
    document.getElementById('infoDosen')     .innerText = rekapData.jadwal.dosen?.nama ?? '-';
    document.getElementById('infoPertemuan') .innerText = rekapData.sesis.length + ' pertemuan';

    // Render tabel
    const tbody = document.getElementById('bodyRekap');
    if (rekapData.rekap.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Belum ada data absensi</td></tr>';
    } else {
        tbody.innerHTML = rekapData.rekap.map((m, i) => {
            const persen      = m.persentase;
            const warna       = persen >= 75 ? 'success' : persen >= 50 ? 'warning' : 'danger';
            const status      = persen >= 75 ? 'Lulus' : 'Tidak Lulus';
            const statusColor = persen >= 75 ? 'success' : 'danger';

            return `
                <tr>
                    <td>${i + 1}</td>
                    <td><span class="badge bg-dark">${m.nim}</span></td>
                    <td>${m.nama}</td>
                    <td class="text-center"><span class="badge bg-success">${m.hadir}</span></td>
                    <td class="text-center"><span class="badge bg-danger">${m.alpha}</span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark">${m.izin}</span></td>
                    <td class="text-center"><span class="badge bg-info text-dark">${m.sakit}</span></td>
                    <td class="text-center" style="min-width:120px">
                        <div class="progress" style="height:20px">
                            <div class="progress-bar bg-${warna}" style="width:${persen}%">
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
function exportExcel() {
    const jadwalId      = document.getElementById('filterJadwal').value;
    const tanggalDari   = document.getElementById('tanggalDari').value;
    const tanggalSampai = document.getElementById('tanggalSampai').value;

    let url = `/admin/rekap/export-excel?jadwal_id=${jadwalId}`;
    if (tanggalDari)   url += `&tanggal_dari=${tanggalDari}`;
    if (tanggalSampai) url += `&tanggal_sampai=${tanggalSampai}`;
    window.open(url, '_blank');
}

// ── Export PDF ──
function exportPdf() {
    const jadwalId      = document.getElementById('filterJadwal').value;
    const tanggalDari   = document.getElementById('tanggalDari').value;
    const tanggalSampai = document.getElementById('tanggalSampai').value;

    let url = `/admin/rekap/export-pdf?jadwal_id=${jadwalId}`;
    if (tanggalDari)   url += `&tanggal_dari=${tanggalDari}`;
    if (tanggalSampai) url += `&tanggal_sampai=${tanggalSampai}`;
    window.open(url, '_blank');
}

init();
</script>
@endpush