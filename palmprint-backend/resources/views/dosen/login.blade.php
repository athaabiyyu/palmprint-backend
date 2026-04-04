<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dosen - Palmprint</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f4f8; }
        .login-card { max-width: 420px; margin: 100px auto; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="card border-0 shadow">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-person-badge fs-1 text-primary"></i>
                <h4 class="fw-bold mt-2">Login Dosen</h4>
                <p class="text-muted small">Sistem Absensi Palmprint</p>
            </div>

            <div id="alertBox" class="alert d-none"></div>

            <div class="mb-3">
                <label class="form-label fw-semibold">NIP</label>
                <input type="text" id="nip" class="form-control" placeholder="Masukkan NIP">
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <input type="password" id="password" class="form-control" placeholder="Masukkan password">
            </div>
            <button class="btn btn-primary w-100" onclick="login()" id="btnLogin">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
async function login() {
    const nip      = document.getElementById('nip').value;
    const password = document.getElementById('password').value;
    const alertBox = document.getElementById('alertBox');
    const btnLogin = document.getElementById('btnLogin');

    if (!nip || !password) {
        showAlert('NIP dan password harus diisi!', 'danger');
        return;
    }

    btnLogin.disabled    = true;
    btnLogin.innerHTML   = '<span class="spinner-border spinner-border-sm me-1"></span> Loading...';

    try {
        const res = await axios.post('/api/dosen/login', { nip, password });

        // Simpan token & data dosen di localStorage
        localStorage.setItem('dosen_token', res.data.token);
        localStorage.setItem('dosen_data',  JSON.stringify(res.data.data));

        // Redirect ke dashboard
        window.location.href = '/dosen/dashboard';

    } catch (e) {
        showAlert(e.response?.data?.message ?? 'Login gagal', 'danger');
        btnLogin.disabled  = false;
        btnLogin.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Login';
    }
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.className = `alert alert-${type}`;
    alertBox.innerText = message;
    alertBox.classList.remove('d-none');
}

// Kalau sudah login, redirect langsung
if (localStorage.getItem('dosen_token')) {
    window.location.href = '/dosen/dashboard';
}
</script>
</body>
</html>