<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sistem Absensi Palmprint</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
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
                    <a href="/admin/dashboard" class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                    <a href="/admin/jurusan" class="nav-link">
                        <i class="bi bi-building me-1"></i> Jurusan & Prodi
                    </a>
                    <a href="/admin/semester" class="nav-link {{ request()->is('admin/semester') ? 'active' : '' }}">
                        <i class="bi bi-calendar3 me-2"></i> Semester
                    </a>
                    <a href="/admin/kelas" class="nav-link {{ request()->is('admin/kelas') ? 'active' : '' }}">
                        <i class="bi bi-building me-2"></i> Kelas
                    </a>
                    <a href="/admin/dosen" class="nav-link {{ request()->is('admin/dosen') ? 'active' : '' }}">
                        <i class="bi bi-person-badge me-2"></i> Dosen
                    </a>
                    <a href="/admin/matkul" class="nav-link {{ request()->is('admin/matkul') ? 'active' : '' }}">
                        <i class="bi bi-book me-2"></i> Mata Kuliah
                    </a>
                    <a href="/admin/jadwal" class="nav-link {{ request()->is('admin/jadwal') ? 'active' : '' }}">
                        <i class="bi bi-table me-2"></i> Jadwal
                    </a>
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
