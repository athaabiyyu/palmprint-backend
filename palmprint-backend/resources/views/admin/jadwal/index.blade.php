@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Manajemen Jadwal</h4>
        <button class="btn btn-primary" onclick="showModal()">
            <i class="bi bi-plus-lg me-1"></i> Tambah Jadwal
        </button>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter per Semester</label>
                    <select id="filterSemester" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Semester --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter per Kelas</label>
                    <select id="filterKelas" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Kelas --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Filter per Hari</label>
                    <select id="filterHari" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Hari --</option>
                        <option value="senin">Senin</option>
                        <option value="selasa">Selasa</option>
                        <option value="rabu">Rabu</option>
                        <option value="kamis">Kamis</option>
                        <option value="jumat">Jumat</option>
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
                        <th>Semester</th>
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
                    <tr>
                        <td colspan="9" class="text-center">Memuat data...</td>
                    </tr>
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
                        <label class="form-label fw-semibold">Semester</label>
                        <select id="semesterId" class="form-select">
                            <option value="">-- Pilih Semester --</option>
                        </select>
                    </div>
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
                        <label class="form-label fw-semibold">
                            Ruangan <span class="text-muted">(opsional)</span>
                        </label>
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
        const modal = new bootstrap.Modal(document.getElementById('modalJadwal'));
        const hariMap = {
            senin: 'Senin',
            selasa: 'Selasa',
            rabu: 'Rabu',
            kamis: 'Kamis',
            jumat: 'Jumat'
        };
        const hariBadge = {
            senin: 'primary',
            selasa: 'success',
            rabu: 'warning',
            kamis: 'danger',
            jumat: 'info'
        };

        let semesterAktifId = null; // ← simpan id semester aktif

        // ── Load Dropdowns ──
        async function loadDropdowns() {
            const [resSemester, resKelas, resMatkul, resDosen] = await Promise.all([
                axios.get('/api/admin/semesters'),
                axios.get('/api/admin/kelas'),
                axios.get('/api/admin/matkuls'),
                axios.get('/api/admin/dosens'),
            ]);

            const filterSemester = document.getElementById('filterSemester');
            const semesterSelect = document.getElementById('semesterId');

            filterSemester.innerHTML = '<option value="">-- Semua Semester --</option>';
            semesterSelect.innerHTML = '<option value="">-- Pilih Semester --</option>';

            resSemester.data.forEach(s => {
                // Tanpa label "(Aktif)" supaya nama bersih
                const label = s.nama;

                if (s.is_active) semesterAktifId = String(s.id);

                filterSemester.innerHTML +=
                    `<option value="${s.id}">${label}${s.is_active ? ' ✓' : ''}</option>`;
                semesterSelect.innerHTML +=
                    `<option value="${s.id}">${label}${s.is_active ? ' ✓' : ''}</option>`;
            });

            if (semesterAktifId) {
                semesterSelect.value = semesterAktifId; 
            }

            // Kelas — filter & modal
            const filterKelas = document.getElementById('filterKelas');
            const kelasSelect = document.getElementById('kelasId');

            filterKelas.innerHTML = '<option value="">-- Semua Kelas --</option>';
            kelasSelect.innerHTML = '<option value="">-- Pilih Kelas --</option>';

            resKelas.data.forEach(k => {
                filterKelas.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
                kelasSelect.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
            });

            const matkulSelect = document.getElementById('matkulId');
            matkulSelect.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
            resMatkul.data.forEach(m => {
                matkulSelect.innerHTML += `<option value="${m.id}">${m.kode} - ${m.nama}</option>`;
            });

            const dosenSelect = document.getElementById('dosenId');
            dosenSelect.innerHTML = '<option value="">-- Pilih Dosen --</option>';
            resDosen.data.forEach(d => {
                dosenSelect.innerHTML += `<option value="${d.id}">${d.nama}</option>`;
            });

            console.log('semesterAktifId:', semesterAktifId);
            console.log('filterSemester.value setelah set:', document.getElementById('filterSemester').value);

            loadData();
        }

        // ── Load Data Jadwal ──
        async function loadData() {
            const tbody = document.getElementById('tableJadwal');
            const semesterId = document.getElementById('filterSemester').value;
            const kelasId = document.getElementById('filterKelas').value;
            const hari = document.getElementById('filterHari').value;

            tbody.innerHTML = '<tr><td colspan="9" class="text-center">Memuat data...</td></tr>';

            // Bangun query param
            const params = new URLSearchParams();
            if (semesterId) params.append('semester_id', semesterId);
            if (kelasId) params.append('kelas_id', kelasId);

            const res = await axios.get(`/api/admin/jadwals?${params.toString()}`);
            let data = res.data;

            // Filter hari di frontend (ringan, data sudah sedikit)
            if (hari) data = data.filter(j => j.hari === hari);

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Belum ada data</td></tr>';
                return;
            }

            tbody.innerHTML = data.map((j, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>
                <small class="text-muted">${j.semester ? j.semester.nama : '-'}</small>
            </td>
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
                <button class="btn btn-warning btn-sm me-1"
                    onclick="edit(
                        ${j.id}, ${j.semester_id}, ${j.kelas_id},
                        ${j.mata_kuliah_id}, ${j.dosen_id},
                        '${j.hari}', '${j.jam_mulai}', '${j.jam_selesai}',
                        '${j.ruangan ?? ''}'
                    )">
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
            document.getElementById('jadwalId').value = '';
            document.getElementById('semesterId').value = semesterAktifId ? String(semesterAktifId) : '';
            document.getElementById('kelasId').value = '';
            document.getElementById('matkulId').value = '';
            document.getElementById('dosenId').value = '';
            document.getElementById('hari').value = 'senin';
            document.getElementById('jamMulai').value = '';
            document.getElementById('jamSelesai').value = '';
            document.getElementById('ruangan').value = '';
            modal.show();
        }

        // ── Show Modal Edit ──
        function edit(id, semesterId, kelasId, matkulId, dosenId, hari, jamMulai, jamSelesai, ruangan) {
            document.getElementById('modalTitle').innerText = 'Edit Jadwal';
            document.getElementById('jadwalId').value = id;
            document.getElementById('semesterId').value = semesterId;
            document.getElementById('kelasId').value = kelasId;
            document.getElementById('matkulId').value = matkulId;
            document.getElementById('dosenId').value = dosenId;
            document.getElementById('hari').value = hari;
            document.getElementById('jamMulai').value = jamMulai;
            document.getElementById('jamSelesai').value = jamSelesai;
            document.getElementById('ruangan').value = ruangan;
            modal.show();
        }

        // ── Simpan ──
        async function simpan() {
            const id = document.getElementById('jadwalId').value;
            const data = {
                semester_id: document.getElementById('semesterId').value,
                kelas_id: document.getElementById('kelasId').value,
                mata_kuliah_id: document.getElementById('matkulId').value,
                dosen_id: document.getElementById('dosenId').value,
                hari: document.getElementById('hari').value,
                jam_mulai: document.getElementById('jamMulai').value,
                jam_selesai: document.getElementById('jamSelesai').value,
                ruangan: document.getElementById('ruangan').value,
            };

            if (!data.semester_id || !data.kelas_id || !data.mata_kuliah_id || !data.dosen_id || !data.jam_mulai || !
                data.jam_selesai) {
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

        // ── Hapus ──
        async function hapus(id) {
            if (!confirm('Yakin hapus jadwal ini?')) return;
            try {
                await axios.delete(`/api/admin/jadwals/${id}`);
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menghapus jadwal');
            }
        }

        loadDropdowns();
    </script>
@endpush
