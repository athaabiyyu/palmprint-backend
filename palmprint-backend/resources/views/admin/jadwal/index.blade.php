@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Jadwal</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
    </button>
</div>

<!-- Filter per Kelas -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter per Kelas</label>
                <select id="filterKelas" class="form-select" onchange="loadData()">
                    <option value="">-- Semua Kelas --</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Jadwal -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Kelas</th>
                    <th>Mata Kuliah</th>
                    <th>Dosen</th>
                    <th>Hari</th>
                    <th>Jam</th>
                    <th>Ruangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableJadwal">
                <tr><td colspan="8" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalJadwal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Jadwal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="jadwalId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kelas</label>
                    <select id="kelasId" class="form-select">
                        <option value="">-- Pilih Kelas --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Mata Kuliah</label>
                    <select id="matkulId" class="form-select">
                        <option value="">-- Pilih Mata Kuliah --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Dosen</label>
                    <select id="dosenId" class="form-select">
                        <option value="">-- Pilih Dosen --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Hari</label>
                    <select id="hari" class="form-select">
                        <option value="senin">Senin</option>
                        <option value="selasa">Selasa</option>
                        <option value="rabu">Rabu</option>
                        <option value="kamis">Kamis</option>
                        <option value="jumat">Jumat</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Jam Mulai</label>
                        <input type="time" id="jamMulai" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Jam Selesai</label>
                        <input type="time" id="jamSelesai" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Ruangan <span class="text-muted">(opsional)</span></label>
                    <input type="text" id="ruangan" class="form-control" placeholder="contoh: Lab TI 1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="simpan()">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const modal    = new bootstrap.Modal(document.getElementById('modalJadwal'));
const hariMap  = {
    senin: 'Senin', selasa: 'Selasa', rabu: 'Rabu',
    kamis: 'Kamis', jumat: 'Jumat'
};
const hariBadge = {
    senin: 'primary', selasa: 'success', rabu: 'warning',
    kamis: 'danger',  jumat: 'info'
};

// ── Load Dropdown ──
async function loadDropdowns() {
    const [resKelas, resMatkul, resDosen] = await Promise.all([
        axios.get('/api/admin/kelas'),
        axios.get('/api/admin/matkuls'),
        axios.get('/api/admin/dosens'),
    ]);

    // Filter kelas
    const filterKelas = document.getElementById('filterKelas');
    filterKelas.innerHTML = '<option value="">-- Semua Kelas --</option>';
    resKelas.data.forEach(k => {
        filterKelas.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
    });

    // Modal dropdown kelas
    const kelasSelect = document.getElementById('kelasId');
    kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';
    resKelas.data.forEach(k => {
        kelasSelect.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
    });

    // Modal dropdown matkul
    const matkulSelect = document.getElementById('matkulId');
    matkulSelect.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
    resMatkul.data.forEach(m => {
        matkulSelect.innerHTML += `<option value="${m.id}">${m.kode} - ${m.nama}</option>`;
    });

    // Modal dropdown dosen
    const dosenSelect = document.getElementById('dosenId');
    dosenSelect.innerHTML = '<option value="">-- Pilih Dosen --</option>';
    resDosen.data.forEach(d => {
        dosenSelect.innerHTML += `<option value="${d.id}">${d.nama}</option>`;
    });
}

// ── Load Data Jadwal ──
async function loadData() {
    const kelasId = document.getElementById('filterKelas').value;
    const tbody   = document.getElementById('tableJadwal');

    tbody.innerHTML = '<tr><td colspan="8" class="text-center">Memuat data...</td></tr>';

    let url = '/api/admin/jadwals';
    if (kelasId) url = `/api/admin/jadwals/kelas/${kelasId}`;

    const res  = await axios.get(url);
    const data = res.data;

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((j, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${j.kelas ? j.kelas.nama : '-'}</td>
            <td>
                <div>${j.mata_kuliah ? j.mata_kuliah.nama : '-'}</div>
                <small class="text-muted">${j.mata_kuliah ? j.mata_kuliah.sks + ' SKS' : ''}</small>
            </td>
            <td>${j.dosen ? j.dosen.nama : '-'}</td>
            <td>
                <span class="badge bg-${hariBadge[j.hari]}">
                    ${hariMap[j.hari]}
                </span>
            </td>
            <td>
                <i class="bi bi-clock me-1"></i>
                ${j.jam_mulai} - ${j.jam_selesai}
            </td>
            <td>${j.ruangan ?? '-'}</td>
            <td>
                <button class="btn btn-warning btn-sm me-1" onclick="edit(${j.id}, ${j.kelas_id}, ${j.mata_kuliah_id}, ${j.dosen_id}, '${j.hari}', '${j.jam_mulai}', '${j.jam_selesai}', '${j.ruangan ?? ''}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="hapus(${j.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Jadwal';
    document.getElementById('jadwalId').value       = '';
    document.getElementById('kelasId').value        = '';
    document.getElementById('matkulId').value       = '';
    document.getElementById('dosenId').value        = '';
    document.getElementById('hari').value           = 'senin';
    document.getElementById('jamMulai').value       = '';
    document.getElementById('jamSelesai').value     = '';
    document.getElementById('ruangan').value        = '';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, kelasId, matkulId, dosenId, hari, jamMulai, jamSelesai, ruangan) {
    document.getElementById('modalTitle').innerText = 'Edit Jadwal';
    document.getElementById('jadwalId').value       = id;
    document.getElementById('kelasId').value        = kelasId;
    document.getElementById('matkulId').value       = matkulId;
    document.getElementById('dosenId').value        = dosenId;
    document.getElementById('hari').value           = hari;
    document.getElementById('jamMulai').value       = jamMulai;
    document.getElementById('jamSelesai').value     = jamSelesai;
    document.getElementById('ruangan').value        = ruangan;
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id   = document.getElementById('jadwalId').value;
    const data = {
        kelas_id       : document.getElementById('kelasId').value,
        mata_kuliah_id : document.getElementById('matkulId').value,
        dosen_id       : document.getElementById('dosenId').value,
        hari           : document.getElementById('hari').value,
        jam_mulai      : document.getElementById('jamMulai').value,
        jam_selesai    : document.getElementById('jamSelesai').value,
        ruangan        : document.getElementById('ruangan').value,
    };

    if (!data.kelas_id || !data.mata_kuliah_id || !data.dosen_id || !data.jam_mulai || !data.jam_selesai) {
        alert('Semua field wajib diisi kecuali ruangan!');
        return;
    }

    try {
        if (id) {
            await axios.put(`/api/admin/jadwals/${id}`, data);
        } else {
            await axios.post('/api/admin/jadwals', data);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

async function hapus(id) {
    if (!confirm('Yakin hapus jadwal ini?')) return;
    await axios.delete(`/api/admin/jadwals/${id}`);
    loadData();
}

loadDropdowns();
loadData();
</script>
@endpush