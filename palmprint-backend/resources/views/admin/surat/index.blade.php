@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Manajemen Surat</h4>
        <div class="d-flex gap-2">
            <span class="badge bg-warning text-dark fs-6" id="totalPending">0 Pending</span>
        </div>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Status</label>
                    <select id="filterStatus" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Status --</option>
                        <option value="pending">Pending</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter Prodi</label>
                    <select id="filterProdi" class="form-select" onchange="loadData()">
                        <option value="">-- Semua Prodi --</option>
                    </select>
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
                            <th>Mahasiswa</th>
                            <th>Mata Kuliah</th>
                            <th>Kelas</th>
                            <th>Tanggal Sesi</th>
                            <th>Jenis</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Diajukan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tableSurat">
                        <tr>
                            <td colspan="10" class="text-center">Memuat data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Review -->
    <div class="modal fade" id="modalReview" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review Surat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="suratId">

                    <!-- Info Surat -->
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted">Mahasiswa</small>
                                    <div class="fw-bold" id="reviewNama">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">NIM</small>
                                    <div class="fw-bold" id="reviewNim">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Mata Kuliah</small>
                                    <div class="fw-bold" id="reviewMatkul">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Tanggal Sesi</small>
                                    <div class="fw-bold" id="reviewTanggal">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Jenis</small>
                                    <div class="fw-bold" id="reviewJenis">-</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Keterangan Mahasiswa</small>
                                    <div id="reviewKeterangan" class="fst-italic">-</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Link Surat</small>
                                    <div>
                                        <a id="reviewLink" href="#" target="_blank"
                                            class="btn btn-outline-primary btn-sm mt-1">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>
                                            Buka Surat di Drive
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Admin -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Catatan Admin
                            <span class="text-danger" id="catatanRequired">
                                (wajib diisi jika menolak)
                            </span>
                        </label>
                        <textarea id="catatanAdmin" class="form-control" rows="3" placeholder="Isi catatan jika menolak surat..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Batal
                    </button>
                    <button type="button" class="btn btn-danger" onclick="review('tolak')">
                        <i class="bi bi-x-circle me-1"></i>Tolak
                    </button>
                    <button type="button" class="btn btn-success" onclick="review('setujui')">
                        <i class="bi bi-check-circle me-1"></i>Setujui
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail (untuk surat yang sudah direview) -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Surat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="card border-0 bg-light mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2">
                                <div class="col-6">
                                    <small class="text-muted">Mahasiswa</small>
                                    <div class="fw-bold" id="detailNama">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">NIM</small>
                                    <div class="fw-bold" id="detailNim">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Mata Kuliah</small>
                                    <div class="fw-bold" id="detailMatkul">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Tanggal Sesi</small>
                                    <div class="fw-bold" id="detailTanggal">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Jenis</small>
                                    <div class="fw-bold" id="detailJenis">-</div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Status</small>
                                    <div id="detailStatus">-</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Keterangan Mahasiswa</small>
                                    <div id="detailKeterangan" class="fst-italic">-</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">Catatan Admin</small>
                                    <div id="detailCatatan" class="text-danger fst-italic">-</div>
                                </div>
                                <div class="col-12">
                                    <a id="detailLink" href="#" target="_blank"
                                        class="btn btn-outline-primary btn-sm mt-1">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>
                                        Buka Surat di Drive
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const modalReview = new bootstrap.Modal(document.getElementById('modalReview'));
        const modalDetail = new bootstrap.Modal(document.getElementById('modalDetail'));

        const statusBadge = {
            pending: '<span class="badge bg-warning text-dark">Pending</span>',
            disetujui: '<span class="badge bg-success">Disetujui</span>',
            ditolak: '<span class="badge bg-danger">Ditolak</span>',
        };

        const jenisBadge = {
            izin: '<span class="badge bg-info text-dark">Izin</span>',
            sakit: '<span class="badge bg-primary">Sakit</span>',
        };

        // ── Load Dropdowns ──
        async function loadDropdowns() {
            const res = await axios.get('/api/admin/prodis');
            const filterProdi = document.getElementById('filterProdi');
            filterProdi.innerHTML = '<option value="">-- Semua Prodi --</option>';
            res.data.forEach(p => {
                filterProdi.innerHTML += `<option value="${p.id}">${p.kode} - ${p.nama}</option>`;
            });

            // Default filter ke pending
            document.getElementById('filterStatus').value = 'pending';
            loadData();
        }

        // ── Load Data ──
        async function loadData() {
            const tbody = document.getElementById('tableSurat');
            const status = document.getElementById('filterStatus').value;
            const prodiId = document.getElementById('filterProdi').value;

            tbody.innerHTML = '<tr><td colspan="10" class="text-center">Memuat data...</td></tr>';

            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (prodiId) params.append('prodi_id', prodiId);

            const res = await axios.get(`/api/admin/surats?${params.toString()}`);
            const data = res.data;

            // Update total pending
            const totalPending = data.filter(s => s.status === 'pending').length;
            document.getElementById('totalPending').innerText = `${totalPending} Pending`;

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">Belum ada data</td></tr>';
                return;
            }

            tbody.innerHTML = data.map((s, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>
                <div class="fw-semibold">${s.mahasiswa?.nama ?? '-'}</div>
                <small class="text-muted">${s.mahasiswa?.nim ?? '-'}</small>
            </td>
            <td>${s.sesi_absensi?.jadwal?.mata_kuliah?.nama ?? '-'}</td>
            <td>
                <span class="badge bg-secondary">
                    ${s.sesi_absensi?.jadwal?.kelas?.nama ?? '-'}
                </span>
            </td>
            <td>${s.sesi_absensi?.tanggal ?? '-'}</td>
            <td>${jenisBadge[s.jenis] ?? s.jenis}</td>
            <td>
                <small class="text-muted">
                    ${s.keterangan ? s.keterangan.substring(0, 40) + (s.keterangan.length > 40 ? '...' : '') : '-'}
                </small>
            </td>
            <td>${statusBadge[s.status] ?? s.status}</td>
            <td><small>${new Date(s.created_at).toLocaleDateString('id-ID')}</small></td>
            <td>
                ${s.status === 'pending'
                    ? `<button class="btn btn-primary btn-sm"
                                onclick="showReview(${JSON.stringify(s).replace(/"/g, '&quot;')})">
                                <i class="bi bi-eye me-1"></i>Review
                            </button>`
                    : `<button class="btn btn-outline-secondary btn-sm"
                                onclick="showDetail(${JSON.stringify(s).replace(/"/g, '&quot;')})">
                                <i class="bi bi-eye me-1"></i>Detail
                            </button>`
                }
            </td>
        </tr>
    `).join('');
        }

        // ── Show Modal Review ──
        function showReview(s) {
            document.getElementById('suratId').value = s.id;
            document.getElementById('reviewNama').innerText = s.mahasiswa?.nama ?? '-';
            document.getElementById('reviewNim').innerText = s.mahasiswa?.nim ?? '-';
            document.getElementById('reviewMatkul').innerText = s.sesi_absensi?.jadwal?.mata_kuliah?.nama ?? '-';
            document.getElementById('reviewTanggal').innerText = s.sesi_absensi?.tanggal ?? '-';
            document.getElementById('reviewJenis').innerText = s.jenis === 'izin' ? 'Izin' : 'Sakit';
            document.getElementById('reviewKeterangan').innerText = s.keterangan ?? '-';
            document.getElementById('reviewLink').href = s.link_drive;
            document.getElementById('catatanAdmin').value = '';
            modalReview.show();
        }

        // ── Show Modal Detail ──
        function showDetail(s) {
            document.getElementById('detailNama').innerText = s.mahasiswa?.nama ?? '-';
            document.getElementById('detailNim').innerText = s.mahasiswa?.nim ?? '-';
            document.getElementById('detailMatkul').innerText = s.sesi_absensi?.jadwal?.mata_kuliah?.nama ?? '-';
            document.getElementById('detailTanggal').innerText = s.sesi_absensi?.tanggal ?? '-';
            document.getElementById('detailJenis').innerText = s.jenis === 'izin' ? 'Izin' : 'Sakit';
            document.getElementById('detailStatus').innerHTML = statusBadge[s.status];
            document.getElementById('detailKeterangan').innerText = s.keterangan ?? '-';
            document.getElementById('detailCatatan').innerText = s.catatan_admin ?? '-';
            document.getElementById('detailLink').href = s.link_drive;
            modalDetail.show();
        }

        // ── Review Surat ──
        async function review(aksi) {
            const id = document.getElementById('suratId').value;
            const catatan = document.getElementById('catatanAdmin').value;

            if (aksi === 'tolak' && !catatan) {
                alert('Catatan penolakan wajib diisi!');
                return;
            }

            const konfirmasi = aksi === 'setujui' ?
                'Yakin setujui surat ini? Status absensi mahasiswa akan diupdate.' :
                'Yakin tolak surat ini?';

            if (!confirm(konfirmasi)) return;

            try {
                const res = await axios.put(`/api/admin/surats/${id}/review`, {
                    aksi: aksi,
                    catatan_admin: catatan,
                });
                alert(res.data.message);
                modalReview.hide();
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        loadDropdowns();
    </script>
@endpush
