@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Manajemen Mahasiswa</h4>
        <span class="badge bg-secondary fs-6" id="totalMahasiswa">Memuat...</span>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter per Prodi</label>
                    <select id="filterProdi" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Prodi --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter per Kelas</label>
                    <select id="filterKelas" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Kelas --</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Palmprint</label>
                    <select id="filterPalmprint" class="form-select" onchange="loadData()">
                        <option value="">-- Semua --</option>
                        <option value="sudah">Sudah Palmprint</option>
                        <option value="belum">Belum Palmprint</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Status</label>
                    <select id="filterStatus" class="form-select" onchange="loadData()">
                        <option value="">-- Semua --</option>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Prodi</th>
                        <th>Palmprint</th>
                        <th>Status</th>
                        <th>Tgl Daftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableMahasiswa">
                    <tr>
                        <td colspan="9" class="text-center">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Pindah Kelas -->
    <div class="modal fade" id="modalPindahKelas" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pindah Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="pindahMahasiswaId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mahasiswa</label>
                        <input type="text" id="pindahMahasiswaNama" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kelas Saat Ini</label>
                        <input type="text" id="pindahKelasLama" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pindah ke Kelas</label>
                        <select id="pindahKelasId" class="form-select">
                            <option value="">-- Pilih Kelas Baru --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="simpanPindahKelas()">Pindah</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div class="modal fade" id="modalResetPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="resetMahasiswaId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mahasiswa</label>
                        <input type="text" id="resetMahasiswaNama" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru</label>
                        <input type="password" id="resetPassword" class="form-control" placeholder="Minimal 6 karakter">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-warning" onclick="simpanResetPassword()">Reset</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const modalPindahKelas = new bootstrap.Modal(document.getElementById('modalPindahKelas'));
        const modalResetPassword = new bootstrap.Modal(document.getElementById('modalResetPassword'));

        let allKelas = [];

        // ── Load Dropdowns ──
        async function loadDropdowns() {
            const [resProdi, resKelas] = await Promise.all([
                axios.get('/api/admin/prodis'),
                axios.get('/api/admin/kelas'),
            ]);

            allKelas = resKelas.data;

            // Filter Prodi
            const filterProdi = document.getElementById('filterProdi');
            filterProdi.innerHTML = '<option value="">-- Semua Prodi --</option>';
            resProdi.data.forEach(p => {
                filterProdi.innerHTML += `<option value="${p.id}">${p.kode} - ${p.nama}</option>`;
            });

            // Filter Kelas
            updateFilterKelas('');




            loadData();
        }

        // ── Update Filter Kelas by Prodi ──
        function updateFilterKelas(prodiId) {
            const filterKelas = document.getElementById('filterKelas');
            const filtered = prodiId ?
                allKelas.filter(k => String(k.prodi_id) === String(prodiId)) :
                allKelas;

            filterKelas.innerHTML = '<option value="">-- Semua Kelas --</option>';
            filtered.forEach(k => {
                filterKelas.innerHTML += `<option value="${k.id}">${k.nama}</option>`;
            });
        }

        // ── Load Data ──
        async function loadData() {
            const tbody = document.getElementById('tableMahasiswa');
            const prodiId = document.getElementById('filterProdi').value;
            const kelasId = document.getElementById('filterKelas').value;
            const palmprint = document.getElementById('filterPalmprint').value;
            const status = document.getElementById('filterStatus').value;

            // Update filter kelas saat prodi berubah
            updateFilterKelas(prodiId);

            tbody.innerHTML = '<tr><td colspan="9" class="text-center">Memuat data...</td></tr>';

            const params = new URLSearchParams();
            if (prodiId) params.append('prodi_id', prodiId);
            if (kelasId) params.append('kelas_id', kelasId);
            if (palmprint) params.append('palmprint', palmprint);

            const res = await axios.get(`/api/admin/mahasiswas?${params.toString()}`);
            let data = res.data;

            // Filter status di frontend
            if (status === 'aktif') data = data.filter(m => m.is_active);
            if (status === 'nonaktif') data = data.filter(m => !m.is_active);

            // Update total
            document.getElementById('totalMahasiswa').innerText = `${data.length} Mahasiswa`;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">Belum ada data</td></tr>';
                return;
            }

            tbody.innerHTML = data.map((m, i) => `
        <tr class="${!m.is_active ? 'table-secondary' : ''}">
            <td>${i + 1}</td>
            <td><span class="badge bg-dark">${m.nim}</span></td>
            <td>
                ${m.nama}
                ${!m.is_active ? '<span class="badge bg-danger ms-1">Nonaktif</span>' : ''}
            </td>
            <td>${m.kelas ?? '-'}</td>
            <td><small class="text-muted">${m.prodi ?? '-'}</small></td>
            <td>
                ${m.sudah_palmprint
                    ? '<span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Sudah</span>'
                    : '<span class="badge bg-warning text-dark"><i class="bi bi-x-lg me-1"></i>Belum</span>'
                }
            </td>
            <td>
                ${m.is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>'
                }
            </td>
            <td><small>${m.created_at}</small></td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-info btn-sm"
                        onclick="showPindahKelas(${m.id}, '${m.nama}', '${m.kelas ?? '-'}', ${m.kelas_id ?? 'null'}, ${m.prodi_id ?? 'null'})"
                        title="Pindah Kelas">
                        <i class="bi bi-arrow-left-right"></i>
                    </button>
                    <button class="btn btn-warning btn-sm"
                        onclick="showResetPassword(${m.id}, '${m.nama}')"
                        title="Reset Password">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-${m.is_active ? 'danger' : 'success'} btn-sm"
                        onclick="toggleAktif(${m.id}, ${m.is_active})"
                        title="${m.is_active ? 'Nonaktifkan' : 'Aktifkan'}">
                        <i class="bi bi-${m.is_active ? 'slash-circle' : 'check-circle'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
        }

        // ── Toggle Aktif ──
        async function toggleAktif(id, isActive) {
            const aksi = isActive ? 'nonaktifkan' : 'aktifkan';
            if (!confirm(`Yakin ${aksi} akun mahasiswa ini?`)) return;

            try {
                const res = await axios.put(`/api/admin/mahasiswas/${id}/toggle-aktif`);
                alert(res.data.message);
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        // ── Show Modal Pindah Kelas ──
        function showPindahKelas(id, nama, kelasLama, kelasLamaId, prodiId) {
            document.getElementById('pindahMahasiswaId').value = id;
            document.getElementById('pindahMahasiswaNama').value = nama;
            document.getElementById('pindahKelasLama').value = kelasLama;

            // Filter dropdown kelas berdasarkan prodi mahasiswa
            const pindahKelasSelect = document.getElementById('pindahKelasId');
            const filtered = prodiId ?
                allKelas.filter(k => String(k.prodi_id) === String(prodiId)) :
                allKelas;

            pindahKelasSelect.innerHTML = '<option value="">-- Pilih Kelas Baru --</option>';
            filtered.forEach(k => {
                // Exclude kelas yang sedang ditempati
                if (k.id !== kelasLamaId) {
                    pindahKelasSelect.innerHTML +=
                        `<option value="${k.id}">${k.nama}</option>`;
                }
            });

            modalPindahKelas.show();
        }

        // ── Simpan Pindah Kelas ──
        async function simpanPindahKelas() {
            const id = document.getElementById('pindahMahasiswaId').value;
            const kelasId = document.getElementById('pindahKelasId').value;

            if (!kelasId) {
                alert('Pilih kelas tujuan terlebih dahulu!');
                return;
            }

            try {
                await axios.put(`/api/admin/mahasiswas/${id}/pindah-kelas`, {
                    kelas_id: kelasId
                });
                modalPindahKelas.hide();
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        // ── Show Modal Reset Password ──
        function showResetPassword(id, nama) {
            document.getElementById('resetMahasiswaId').value = id;
            document.getElementById('resetMahasiswaNama').value = nama;
            document.getElementById('resetPassword').value = '';
            modalResetPassword.show();
        }

        // ── Simpan Reset Password ──
        async function simpanResetPassword() {
            const id = document.getElementById('resetMahasiswaId').value;
            const password = document.getElementById('resetPassword').value;

            if (!password || password.length < 6) {
                alert('Password minimal 6 karakter!');
                return;
            }

            try {
                await axios.put(`/api/admin/mahasiswas/${id}/reset-password`, {
                    password
                });
                modalResetPassword.hide();
                alert('Password berhasil direset!');
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        loadDropdowns();
    </script>
@endpush
