<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Palmprint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f0f4f8;
        }

        .navbar {
            background: #1e3a5f;
        }

        .card-jadwal {
            cursor: pointer;
            transition: 0.2s;
        }

        .card-jadwal:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
        }

        .badge-hari {
            font-size: 0.85rem;
        }

        #countdown {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-dark px-4 py-3">
        <span class="navbar-brand fw-bold">
            <i class="bi bi-hand-index-thumb me-2"></i>Palmprint — Dosen
        </span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white" id="namaDosen"></span>
            <button class="btn btn-outline-light btn-sm" onclick="showModalProfil()">
                <i class="bi bi-person-circle me-1"></i>Profil
            </button>
            <button class="btn btn-outline-light btn-sm" onclick="logout()">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </button>
        </div>
    </nav>

    <div class="container py-4">

        <!-- Header hari ini -->
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-0" id="judulJadwal">Jadwal Hari Ini</h5>
                <small class="text-muted" id="tanggalHariIni"></small>
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="loadJadwal()">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>

        <!-- Filter Hari -->
        <div class="d-flex gap-2 mb-4 flex-wrap">
            <button class="btn btn-primary btn-sm filter-hari" data-hari="" onclick="setFilterHari(this, '')">
                Semua
            </button>
            <button class="btn btn-outline-secondary btn-sm filter-hari" data-hari="senin"
                onclick="setFilterHari(this, 'senin')">
                Senin
            </button>
            <button class="btn btn-outline-secondary btn-sm filter-hari" data-hari="selasa"
                onclick="setFilterHari(this, 'selasa')">
                Selasa
            </button>
            <button class="btn btn-outline-secondary btn-sm filter-hari" data-hari="rabu"
                onclick="setFilterHari(this, 'rabu')">
                Rabu
            </button>
            <button class="btn btn-outline-secondary btn-sm filter-hari" data-hari="kamis"
                onclick="setFilterHari(this, 'kamis')">
                Kamis
            </button>
            <button class="btn btn-outline-secondary btn-sm filter-hari" data-hari="jumat"
                onclick="setFilterHari(this, 'jumat')">
                Jumat
            </button>
        </div>

        <!-- Jadwal Cards -->
        <div id="jadwalContainer" class="row g-3">
            <div class="col-12 text-center text-muted">Memuat jadwal...</div>
        </div>
    </div>

    <!-- Modal Buka Sesi -->
    <div class="modal fade" id="modalBukaSesi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buka Sesi Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="jadwalId">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        Mata Kuliah: <strong id="infoMatkul"></strong><br>
                        Kelas: <strong id="infoKelas"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Durasi Absensi</label>
                        <select id="durasi" class="form-select">
                            <option value="10">10 Menit</option>
                            <option value="15" selected>15 Menit</option>
                            <option value="20">20 Menit</option>
                            <option value="30">30 Menit</option>
                            <option value="60">60 Menit</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" onclick="bukaSesi()">
                        <i class="bi bi-unlock me-1"></i> Buka Absensi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Sesi Aktif -->
    <div class="modal fade" id="modalDetailSesi" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div id="detailMatkul" class="fw-bold"></div>
                            <div id="detailKelas" class="text-muted small"></div>
                        </div>
                        <div class="text-center">
                            <div class="text-muted small">Sisa Waktu</div>
                            <div id="countdown">--:--</div>
                        </div>
                    </div>

                    <!-- Statistik -->
                    <div class="row g-2 mb-3" id="statistikAbsen"></div>

                    <!-- Tabel Daftar Hadir -->
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>NIM</th>
                                <th>Nama</th>
                                <th>Status</th>
                                <th>Waktu Absen</th>
                            </tr>
                        </thead>
                        <tbody id="tableDaftarHadir"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-danger" id="btnTutupSesi" onclick="tutupSesi()">
                        <i class="bi bi-lock me-1"></i> Tutup Absensi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Profil -->
    <div class="modal fade" id="modalProfil" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-circle me-2"></i>Profil Saya
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <!-- Tab -->
                    <ul class="nav nav-tabs mb-3" id="tabProfil">
                        <li class="nav-item">
                            <button class="nav-link active" onclick="switchTab('info')">
                                <i class="bi bi-person me-1"></i>Info
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" onclick="switchTab('password')">
                                <i class="bi bi-key me-1"></i>Ganti Password
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Info -->
                    <div id="tabInfo">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">NIP</label>
                            <input type="text" id="profilNip" class="form-control" placeholder="NIP">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama</label>
                            <input type="text" id="profilNama" class="form-control" placeholder="Nama lengkap">
                        </div>
                        <button class="btn btn-primary w-100" onclick="simpanProfil()">
                            <i class="bi bi-check-lg me-1"></i>Simpan Perubahan
                        </button>
                    </div>

                    <!-- Tab Password -->
                    <div id="tabPassword" class="d-none">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            Password default adalah NIP kamu. Segera ganti setelah login pertama!
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password Lama</label>
                            <input type="password" id="passwordLama" class="form-control"
                                placeholder="Masukkan password lama">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Password Baru</label>
                            <input type="password" id="passwordBaru" class="form-control"
                                placeholder="Minimal 6 karakter">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                            <input type="password" id="passwordKonfirmasi" class="form-control"
                                placeholder="Ulangi password baru">
                        </div>
                        <button class="btn btn-warning w-100" onclick="simpanPassword()">
                            <i class="bi bi-key me-1"></i>Ganti Password
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // ── Setup Axios dengan token ──
        const token = localStorage.getItem('dosen_token');
        if (!token) window.location.href = '/dosen/login';

        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        axios.defaults.headers.common['Accept'] = 'application/json';

        // ── Data ──
        const dosen = JSON.parse(localStorage.getItem('dosen_data') ?? '{}');
        let activeSesiId = null;
        let countdownTimer = null;
        let filterHari = null;
        let allJadwals = [];
        let hariSekarang = '';

        // Tambahkan ini
        const hariMap = {
            senin: 'Senin',
            selasa: 'Selasa',
            rabu: 'Rabu',
            kamis: 'Kamis',
            jumat: 'Jumat'
        };

        const modalBuka = new bootstrap.Modal(document.getElementById('modalBukaSesi'));
        const modalDetail = new bootstrap.Modal(document.getElementById('modalDetailSesi'));

        // ── Init ──
        document.getElementById('namaDosen').innerText = dosen.nama ?? '';
        document.getElementById('tanggalHariIni').innerText = new Date().toLocaleDateString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        const modalProfil = new bootstrap.Modal(document.getElementById('modalProfil'));

        // ── Show Modal Profil ──
        function showModalProfil() {
            // Isi dari localStorage dulu
            document.getElementById('profilNip').value = dosen.nip ?? '';
            document.getElementById('profilNama').value = dosen.nama ?? '';

            // Reset tab ke info
            switchTab('info');

            // Reset field password
            document.getElementById('passwordLama').value = '';
            document.getElementById('passwordBaru').value = '';
            document.getElementById('passwordKonfirmasi').value = '';

            modalProfil.show();
        }

        // ── Switch Tab ──
        function switchTab(tab) {
            const tabInfo = document.getElementById('tabInfo');
            const tabPassword = document.getElementById('tabPassword');
            const tabs = document.querySelectorAll('#tabProfil .nav-link');

            if (tab === 'info') {
                tabInfo.classList.remove('d-none');
                tabPassword.classList.add('d-none');
                tabs[0].classList.add('active');
                tabs[1].classList.remove('active');
            } else {
                tabInfo.classList.add('d-none');
                tabPassword.classList.remove('d-none');
                tabs[0].classList.remove('active');
                tabs[1].classList.add('active');
            }
        }

        // ── Simpan Profil ──
        async function simpanProfil() {
            const nip = document.getElementById('profilNip').value;
            const nama = document.getElementById('profilNama').value;

            if (!nip || !nama) {
                alert('NIP dan nama harus diisi!');
                return;
            }

            try {
                const res = await axios.put('/api/dosen/profil', {
                    nip,
                    nama
                });

                // Update localStorage
                const dosenBaru = res.data.data;
                localStorage.setItem('dosen_data', JSON.stringify(dosenBaru));

                // Update tampilan navbar
                document.getElementById('namaDosen').innerText = dosenBaru.nama;

                alert('Profil berhasil diupdate!');
                modalProfil.hide();

            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menyimpan profil');
            }
        }

        // ── Ganti Password ──
        async function simpanPassword() {
            const passwordLama = document.getElementById('passwordLama').value;
            const passwordBaru = document.getElementById('passwordBaru').value;
            const passwordKonfirmasi = document.getElementById('passwordKonfirmasi').value;

            if (!passwordLama || !passwordBaru || !passwordKonfirmasi) {
                alert('Semua field password harus diisi!');
                return;
            }

            if (passwordBaru !== passwordKonfirmasi) {
                alert('Konfirmasi password tidak cocok!');
                return;
            }

            if (passwordBaru.length < 6) {
                alert('Password baru minimal 6 karakter!');
                return;
            }

            try {
                await axios.put('/api/dosen/ganti-password', {
                    password_lama: passwordLama,
                    password_baru: passwordBaru,
                });

                alert('Password berhasil diubah! Silakan login ulang.');

                // Logout otomatis setelah ganti password
                await logout();

            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal mengganti password');
            }
        }

        // ── Load Jadwal Hari Ini ──
        async function loadJadwal() {
            const container = document.getElementById('jadwalContainer');
            container.innerHTML = '<div class="col-12 text-center text-muted">Memuat jadwal...</div>';

            try {
                const res = await axios.get('/api/dosen/jadwal-hari-ini');
                allJadwals = res.data;

                // Simpan hari sekarang dari data
                const today = allJadwals.find(j => j.is_today);
                if (today) hariSekarang = today.hari;

                // Jika belum ada filter, default ke hari ini
                if (filterHari === null) {
                    filterHari = hariSekarang;
                    // Aktifkan tombol hari ini
                    document.querySelectorAll('.filter-hari').forEach(btn => {
                        btn.classList.remove('btn-primary', 'btn-outline-secondary');
                        if (btn.dataset.hari === filterHari) {
                            btn.classList.add('btn-primary');
                        } else {
                            btn.classList.add('btn-outline-secondary');
                        }
                    });
                }

                renderJadwal();

            } catch (e) {
                if (e.response?.status === 401) {
                    localStorage.clear();
                    window.location.href = '/dosen/login';
                }
            }
        }

        // ── Set Filter Hari ──
        function setFilterHari(btn, hari) {
            filterHari = hari;

            // Update tombol aktif
            document.querySelectorAll('.filter-hari').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');

            // Update judul
            const hariLabels = {
                '': 'Semua Jadwal',
                'senin': 'Jadwal Senin',
                'selasa': 'Jadwal Selasa',
                'rabu': 'Jadwal Rabu',
                'kamis': 'Jadwal Kamis',
                'jumat': 'Jadwal Jumat',
            };
            document.getElementById('judulJadwal').innerText =
                hari === hariSekarang ? 'Jadwal Hari Ini' : (hariLabels[hari] ?? 'Semua Jadwal');

            renderJadwal();
        }

        // ── Render Jadwal berdasarkan filter ──
        function renderJadwal() {
            const container = document.getElementById('jadwalContainer');

            // Filter data
            const filtered = filterHari === '' ?
                allJadwals :
                allJadwals.filter(j => j.hari === filterHari);

            if (filtered.length === 0) {
                container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
                    Tidak ada jadwal ${filterHari === hariSekarang ? 'hari ini' : 'di hari ini'}
                </div>
            </div>`;
                return;
            }

            container.innerHTML = filtered.map(j => {
                const sesiAktif = j.sesi_aktif;
                const isToday = j.is_today;

                return `
            <div class="col-md-6">
                <div class="card border-0 shadow-sm card-jadwal ${isToday ? 'border-primary border-2' : 'opacity-75'}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0">${j.mata_kuliah.nama}</h6>
                                <small class="text-muted">${j.kelas.nama} • ${j.mata_kuliah.sks} SKS</small>
                            </div>
                            <div class="d-flex flex-column gap-1 align-items-end">
                                ${isToday
                                    ? '<span class="badge bg-primary">Hari Ini</span>'
                                    : `<span class="badge bg-light text-dark">${hariMap[j.hari]}</span>`
                                }
                                ${sesiAktif
                                    ? '<span class="badge bg-success">Sesi Aktif</span>'
                                    : ''
                                }
                            </div>
                        </div>
                        <div class="text-muted small mb-3">
                            <i class="bi bi-clock me-1"></i>${j.jam_mulai} - ${j.jam_selesai}
                            ${j.ruangan ? `<i class="bi bi-geo-alt ms-2 me-1"></i>${j.ruangan}` : ''}
                        </div>
                        ${sesiAktif
                            ? `<button class="btn btn-success btn-sm w-100"
                                                onclick="lihatDetail(${sesiAktif.id})">
                                                <i class="bi bi-eye me-1"></i> Lihat Absensi
                                               </button>`
                            : isToday
                                ? `<button class="btn btn-primary btn-sm w-100"
                                                    onclick="showModalBuka(${j.id}, '${j.mata_kuliah.nama}', '${j.kelas.nama}')">
                                                    <i class="bi bi-unlock me-1"></i> Buka Absensi
                                                   </button>`
                                : `<button class="btn btn-secondary btn-sm w-100" disabled>
                                                    <i class="bi bi-lock me-1"></i> Bukan Hari Ini
                                                   </button>`
                        }
                    </div>
                </div>
            </div>
        `;
            }).join('');
        }

        // ── Show Modal Buka Sesi ──
        function showModalBuka(jadwalId, matkul, kelas) {
            document.getElementById('jadwalId').value = jadwalId;
            document.getElementById('infoMatkul').innerText = matkul;
            document.getElementById('infoKelas').innerText = kelas;
            modalBuka.show();
        }

        // ── Buka Sesi ──
        async function bukaSesi() {
            const jadwalId = document.getElementById('jadwalId').value;
            const durasi = document.getElementById('durasi').value;

            try {
                await axios.post('/api/dosen/sesi/buka', {
                    jadwal_id: jadwalId,
                    durasi_menit: durasi,
                });
                modalBuka.hide();
                loadJadwal();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal membuka sesi');
            }
        }

        // ── Lihat Detail Sesi Aktif ──
        async function lihatDetail(sesiId) {
            activeSesiId = sesiId;

            try {
                const res = await axios.get(`/api/dosen/sesi/${sesiId}/detail`);
                const data = res.data;

                document.getElementById('detailMatkul').innerText =
                    data.sesi.jadwal.mata_kuliah.nama;
                document.getElementById('detailKelas').innerText =
                    data.sesi.jadwal.kelas.nama + ' • ' + data.sesi.tanggal;

                // Statistik
                const hadir = data.daftar_hadir.filter(m => m.status === 'hadir').length;
                const belum = data.daftar_hadir.filter(m => m.status === 'belum').length;
                const total = data.daftar_hadir.length;

                document.getElementById('statistikAbsen').innerHTML = `
            <div class="col-4">
                <div class="card border-0 bg-success bg-opacity-10 text-center p-2">
                    <div class="fw-bold text-success fs-4">${hadir}</div>
                    <div class="small text-muted">Hadir</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 bg-warning bg-opacity-10 text-center p-2">
                    <div class="fw-bold text-warning fs-4">${belum}</div>
                    <div class="small text-muted">Belum Absen</div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-0 bg-primary bg-opacity-10 text-center p-2">
                    <div class="fw-bold text-primary fs-4">${total}</div>
                    <div class="small text-muted">Total</div>
                </div>
            </div>
        `;

                // Tabel daftar hadir
                document.getElementById('tableDaftarHadir').innerHTML =
                    data.daftar_hadir.map(m => `
                <tr>
                    <td>${m.nim}</td>
                    <td>${m.nama}</td>
                    <td>
                        ${m.status === 'hadir'
                            ? '<span class="badge bg-success">Hadir</span>'
                            : '<span class="badge bg-secondary">Belum</span>'
                        }
                    </td>
                    <td>${m.waktu ? new Date(m.waktu).toLocaleTimeString('id-ID') : '-'}</td>
                </tr>
            `).join('');

                // Countdown timer
                startCountdown(data.sesi.dibuka_at, data.sesi.durasi_menit);

                modalDetail.show();

            } catch (e) {
                alert('Gagal memuat detail sesi');
            }
        }

        // ── Countdown Timer ──
        function startCountdown(dibukaAt, durasiMenit) {
            if (countdownTimer) clearInterval(countdownTimer);

            const batasWaktu = new Date(dibukaAt);
            batasWaktu.setMinutes(batasWaktu.getMinutes() + durasiMenit);

            countdownTimer = setInterval(() => {
                const sekarang = new Date();
                const sisa = batasWaktu - sekarang;

                if (sisa <= 0) {
                    clearInterval(countdownTimer);
                    document.getElementById('countdown').innerText = 'Waktu Habis!';
                    document.getElementById('btnTutupSesi').disabled = true;
                    loadJadwal();
                    return;
                }

                const menit = Math.floor(sisa / 60000);
                const detik = Math.floor((sisa % 60000) / 1000);
                document.getElementById('countdown').innerText =
                    `${String(menit).padStart(2,'0')}:${String(detik).padStart(2,'0')}`;
            }, 1000);
        }

        // ── Tutup Sesi Manual ──
        async function tutupSesi() {
            if (!confirm('Yakin tutup sesi absensi sekarang?')) return;

            try {
                await axios.post(`/api/dosen/sesi/${activeSesiId}/tutup`);
                if (countdownTimer) clearInterval(countdownTimer);
                modalDetail.hide();
                loadJadwal();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menutup sesi');
            }
        }

        // ── Logout ──
        async function logout() {
            try {
                await axios.post('/api/dosen/logout');
            } catch (e) {}
            localStorage.clear();
            window.location.href = '/dosen/login';
        }

        // ── Auto refresh setiap 30 detik ──
        loadJadwal();
        setInterval(loadJadwal, 30000);
    </script>
</body>

</html>
