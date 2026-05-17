<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sistem Absensi Palmprint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #1e3a5f;
        }

        .sidebar .nav-link {
            color: #adb5bd;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .sidebar .brand {
            color: #fff;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .sidebar .nav-section {
            color: #6c757d;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0.5rem 0.75rem 0.25rem;
            margin-top: 0.5rem;
        }

        /* Submenu */
        .sidebar .nav-submenu {
            padding-left: 1rem;
        }

        .sidebar .nav-submenu .nav-link {
            font-size: 0.875rem;
            padding: 0.3rem 0.75rem;
        }

        /* Collapse toggle arrow */
        .sidebar .nav-link[data-bs-toggle="collapse"]::after {
            content: "\F282";
            font-family: "bootstrap-icons";
            float: right;
            transition: transform 0.2s;
        }

        .sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
    </style>
</head>

<body>
    <div class="container-fluid p-0">
        <div class="row g-0">

            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-3">
                <div class="brand mb-4 ps-2">
                    <i class="bi bi-hand-index-thumb"></i> Palmprint Admin
                </div>
                <nav class="nav flex-column gap-1">

                    {{-- Dashboard --}}
                    <a href="/admin/dashboard" class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>

                    {{-- Jurusan & Prodi --}}
                    <a href="/admin/jurusan" class="nav-link {{ request()->is('admin/jurusan') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3 me-2"></i> Jurusan & Prodi
                    </a>

                    {{-- Akademik (Semester + Kelas) --}}
                    <a class="nav-link {{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'active' : '' }}"
                        data-bs-toggle="collapse" href="#menuAkademik"
                        aria-expanded="{{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'true' : 'false' }}">
                        <i class="bi bi-mortarboard me-2"></i> Akademik
                    </a>
                    <div class="collapse nav-submenu {{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'show' : '' }}"
                        id="menuAkademik">
                        <a href="/admin/semester"
                            class="nav-link {{ request()->is('admin/semester') ? 'active' : '' }}">
                            <i class="bi bi-calendar3 me-2"></i> Semester
                        </a>
                        <a href="/admin/kelas" class="nav-link {{ request()->is('admin/kelas') ? 'active' : '' }}">
                            <i class="bi bi-people me-2"></i> Kelas
                        </a>
                    </div>

                    {{-- Perkuliahan (Mata Kuliah + Jadwal) --}}
                    <a class="nav-link {{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'active' : '' }}"
                        data-bs-toggle="collapse" href="#menuPerkuliahan"
                        aria-expanded="{{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'true' : 'false' }}">
                        <i class="bi bi-journal-bookmark me-2"></i> Perkuliahan
                    </a>
                    <div class="collapse nav-submenu {{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'show' : '' }}"
                        id="menuPerkuliahan">
                        <a href="/admin/matkul" class="nav-link {{ request()->is('admin/matkul') ? 'active' : '' }}">
                            <i class="bi bi-book me-2"></i> Mata Kuliah
                        </a>
                        <a href="/admin/jadwal" class="nav-link {{ request()->is('admin/jadwal') ? 'active' : '' }}">
                            <i class="bi bi-table me-2"></i> Jadwal
                        </a>
                    </div>

                    {{-- Dosen --}}
                    <a href="/admin/dosen" class="nav-link {{ request()->is('admin/dosen') ? 'active' : '' }}">
                        <i class="bi bi-person-badge me-2"></i> Dosen
                    </a>

                    <a href="/admin/mahasiswa" class="nav-link {{ request()->is('admin/mahasiswa') ? 'active' : '' }}">
                        <i class="bi bi-mortarboard me-2"></i> Mahasiswa
                    </a>

                    {{-- Rekap Absensi --}}
                    <a href="/admin/rekap" class="nav-link {{ request()->is('admin/rekap') ? 'active' : '' }}">
                        <i class="bi bi-clipboard-data me-2"></i> Rekap Absensi
                    </a>

                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content p-4">
                @yield('content')
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    @stack('scripts')
</body>

</html>
