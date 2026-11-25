<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

session_start();

/**
 * Helper untuk mengamankan output HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Helper format rupiah.
 */
function money(float $number): string
{
    return 'Rp ' . number_format($number, 0, ',', '.');
}

/**
 * Membuat objek mahasiswa berdasarkan jenisnya.
 */
function makeStudent(array $data): Student
{
    $id = trim((string) ($data['id'] ?? ''));
    $name = trim((string) ($data['name'] ?? ''));
    $major = trim((string) ($data['major'] ?? ''));

    $fee = filter_var(
        $data['fee'] ?? null,
        FILTER_VALIDATE_FLOAT
    );

    if ($id === '' || $name === '' || $major === '') {
        throw new InvalidArgumentException(
            'NIM, nama, dan program studi wajib diisi.'
        );
    }

    if ($fee === false || $fee < 0) {
        throw new InvalidArgumentException(
            'Biaya dasar harus berupa angka yang valid.'
        );
    }

    $type = (string) ($data['type'] ?? '');

    if ($type === 'regular') {
        return new RegularStudent(
            $id,
            $name,
            $major,
            (float) $fee
        );
    }

    if ($type === 'scholarship') {
        $discount = filter_var(
            $data['discount'] ?? null,
            FILTER_VALIDATE_FLOAT
        );

        if (
            $discount === false ||
            $discount < 0 ||
            $discount > 100
        ) {
            throw new InvalidArgumentException(
                'Diskon harus berupa angka antara 0 sampai 100.'
            );
        }

        return new ScholarshipStudent(
            $id,
            $name,
            $major,
            (float) $fee,
            (float) $discount
        );
    }

    // Tambahan Tugas 3: InternationalStudent
    if ($type === 'international') {
        return new InternationalStudent(
            $id,
            $name,
            $major,
            (float) $fee
        );
    }

    throw new InvalidArgumentException(
        'Jenis mahasiswa tidak dikenal.'
    );
}

$error = '';
$success = '';

/**
 * Inisialisasi repository.
 */
$repo = new StudentRepository();

/**
 * Mengambil data mahasiswa dari session.
 */
if (!isset($_SESSION['students'])) {
    $_SESSION['students'] = [
        new RegularStudent(
            'M001',
            'Alya',
            'Informatika',
            5000000
        ),
        new ScholarshipStudent(
            'M002',
            'Bima',
            'Sistem Informasi',
            5000000,
            40
        )
    ];
}

foreach ($_SESSION['students'] as $student) {
    if ($student instanceof Student) {
        // Data session dimasukkan ke repository.
        if (!$repo->find($student->getId())) {
            $repo->create($student);
        }
    }
}

/**
 * CSRF token.
 */
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/**
 * Memproses form.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !hash_equals(
                $_SESSION['csrf'],
                (string) ($_POST['csrf'] ?? '')
            )
        ) {
            throw new InvalidArgumentException(
                'Token formulir tidak valid. Muat ulang halaman.'
            );
        }

        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = trim((string) ($_POST['id'] ?? ''));

            if (!$repo->delete($id)) {
                throw new InvalidArgumentException(
                    'Data mahasiswa tidak ditemukan.'
                );
            }

            $success = 'Data mahasiswa berhasil dihapus.';
        } elseif (in_array($action, ['create', 'update'], true)) {
            $oldId = trim((string) ($_POST['old_id'] ?? ''));

            $student = makeStudent([
                'id' => $_POST['id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'major' => $_POST['major'] ?? '',
                'fee' => $_POST['fee'] ?? '',
                'type' => $_POST['type'] ?? '',
                'discount' => $_POST['discount'] ?? 0
            ]);

            if ($action === 'create') {
                $result = $repo->create($student);
            } else {
                $result = $repo->update($oldId, $student);
            }

            if (!$result) {
                throw new InvalidArgumentException(
                    'NIM sudah terdaftar atau data tidak ditemukan.'
                );
            }

            $success = $action === 'create'
                ? 'Data mahasiswa berhasil ditambahkan.'
                : 'Data mahasiswa berhasil diperbarui.';
        } else {
            throw new InvalidArgumentException(
                'Aksi tidak dikenal.'
            );
        }

        // Simpan kembali data repository ke session.
        $_SESSION['students'] = array_values($repo->all());

        $_SESSION['flash'] = $success;

        header('Location: index.php');
        exit;

    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

/**
 * Pesan notifikasi.
 */
if (isset($_SESSION['flash'])) {
    $success = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}

/**
 * Data untuk edit.
 */
$editId = (string) ($_GET['edit'] ?? '');
$edit = $editId !== '' ? $repo->find($editId) : null;

/**
 * Fitur pencarian.
 */
$search = trim((string) ($_GET['search'] ?? ''));

$students = array_filter(
    $repo->all(),
    function (Student $student) use ($search): bool {
        return $search === ''
            || stripos($student->getId(), $search) !== false
            || stripos($student->getName(), $search) !== false
            || stripos($student->getMajor(), $search) !== false;
    }
);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>CRUD Mahasiswa - Praktikum PBO</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #15324d;
        }

        main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 18px;
        }

        header {
            background: #173b5e;
            color: white;
            padding: 28px;
            border-radius: 14px;
            margin-bottom: 24px;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
            color: #dce8f2;
        }

        .panel {
            background: white;
            padding: 24px;
            margin: 20px 0;
            border-radius: 12px;
            box-shadow: 0 5px 18px #16324d14;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        label {
            display: block;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
        }

        button,
        .button {
            display: inline-block;
            border: none;
            padding: 11px 17px;
            border-radius: 7px;
            background: #173b5e;
            color: white;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        button:hover,
        .button:hover {
            background: #24577f;
        }

        .cancel {
            background: #64748b;
        }

        .delete {
            background: #dc3545;
        }

        .edit {
            background: #d49b28;
            color: #17202a;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin: 15px 0;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            min-width: 700px;
        }

        th,
        td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #173b5e;
            color: white;
        }

        tr:hover {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .actions form {
            margin: 0;
        }

        .actions button,
        .actions a {
            padding: 8px 11px;
            font-size: 12px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .search-form input {
            margin: 0;
        }

        .info {
            background: #e0f2fe;
            color: #075985;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.6;
        }

        @media (max-width: 650px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
            }

            header {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<main>

    <header>
        <h1>CRUD Data Mahasiswa</h1>
        <p>Praktikum Pemrograman Berorientasi Objek - PHP</p>
    </header>

    <?php if ($success !== ''): ?>
        <div class="message success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2><?= $edit ? 'Edit Data Mahasiswa' : 'Tambah Mahasiswa' ?></h2>

        <form method="POST">

            <input
                type="hidden"
                name="csrf"
                value="<?= e($_SESSION['csrf']) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="<?= $edit ? 'update' : 'create' ?>"
            >

            <input
                type="hidden"
                name="old_id"
                value="<?= e($edit?->getId() ?? '') ?>"
            >

            <div class="grid">

                <label>
                    NIM
                    <input
                        type="text"
                        name="id"
                        value="<?= e($edit?->getId() ?? '') ?>"
                        required
                        <?= $edit ? 'readonly' : '' ?>
                    >
                </label>

                <label>
                    Nama Mahasiswa
                    <input
                        type="text"
                        name="name"
                        value="<?= e($edit?->getName() ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    Program Studi
                    <input
                        type="text"
                        name="major"
                        value="<?= e($edit?->getMajor() ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    Biaya Dasar
                    <input
                        type="number"
                        name="fee"
                        min="0"
                        value="<?= e($edit?->getBaseFee() ?? '') ?>"
                        required
                    >
                </label>

                <label>
                    Jenis Mahasiswa
                    <select name="type" required>
                        <option value="">-- Pilih Jenis --</option>

                        <option
                            value="regular"
                            <?= $edit?->getType() === 'regular' ? 'selected' : '' ?>
                        >
                            Reguler
                        </option>

                        <option
                            value="scholarship"
                            <?= $edit?->getType() === 'scholarship' ? 'selected' : '' ?>
                        >
                            Beasiswa
                        </option>

                        <!-- Pilihan baru Tugas 3 -->
                        <option
                            value="international"
                            <?= $edit?->getType() === 'international' ? 'selected' : '' ?>
                        >
                            Internasional
                        </option>
                    </select>
                </label>

                <label>
                    Diskon Beasiswa (%)
                    <input
                        type="number"
                        name="discount"
                        min="0"
                        max="100"
                        value="<?= e($edit?->getDiscount() ?? 0) ?>"
                    >
                </label>

            </div>

            <br>

            <button type="submit">
                <?= $edit ? 'Simpan Perubahan' : 'Tambah Mahasiswa' ?>
            </button>

            <?php if ($edit): ?>
                <a href="index.php" class="button cancel">
                    Batal
                </a>
            <?php endif; ?>

        </form>
    </section>

    <section class="panel">
        <h2>Daftar Mahasiswa</h2>

        <form method="GET" class="search-form">
            <input
                type="text"
                name="search"
                placeholder="Cari NIM, nama, atau program studi..."
                value="<?= e($search) ?>"
            >

            <button type="submit">Cari</button>

            <a href="index.php" class="button cancel">
                Reset
            </a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>NIM</th>
                        <th>Nama</th>
                        <th>Program Studi</th>
                        <th>Jenis</th>
                        <th>Biaya Dasar</th>
                        <th>Total Biaya</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (count($students) > 0): ?>

                        <?php foreach ($students as $student): ?>

                            <tr>
                                <td><?= e($student->getId()) ?></td>

                                <td><?= e($student->getName()) ?></td>

                                <td><?= e($student->getMajor()) ?></td>

                                <td><?= e($student->getType()) ?></td>

                                <td>
                                    <?= money($student->getBaseFee()) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= money($student->calculateFee()) ?>
                                    </strong>
                                </td>

                                <td>
                                    <div class="actions">

                                        <a
                                            class="button edit"
                                            href="?edit=<?= urlencode($student->getId()) ?>"
                                        >
                                            Edit
                                        </a>

                                        <form method="POST">
                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e($_SESSION['csrf']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= e($student->getId()) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="delete"
                                            >
                                                Hapus
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7" style="text-align:center">
                                Data mahasiswa belum tersedia.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <h2>Laboratorium OOP - Tugas 2</h2>

        <p>
            Pengujian magic method <code>__call()</code>
            menggunakan method <code>showData()</code>.
        </p>

        <?php
        $presenter = new StudentPresenter();
        ?>

        <div class="info">
            <?= e($presenter->showData(
                'M001',
                'Alya',
                'Informatika'
            )) ?>
        </div>
    </section>

    <section class="panel">
        <h2>Informasi Tugas 3</h2>

        <div class="info">
            Class <strong>InternationalStudent</strong> merupakan child
            dari class <strong>Student</strong>.

            Class tersebut memiliki implementasi
            <code>calculateFee()</code> sendiri untuk menghitung biaya
            mahasiswa internasional.

            Repository tetap menggunakan tipe parent
            <code>Student</code>, sehingga tidak perlu mengetahui
            nama setiap child class.
        </div>
    </section>

</main>

</body>
</html>
