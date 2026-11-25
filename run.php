$p = new StudentPresenter();

// Pemanggilan valid
check(
    $p->showData('M01', 'Alya', 'Informatika')
    === 'NIM: M01, Nama: Alya, Prodi: Informatika',
    '__call menerima tiga argumen string'
);

// Argumen kurang
fails(
    fn() => $p->showData('Alya'),
    InvalidArgumentException::class,
    '__call satu argumen ditolak'
);

// Argumen kurang
fails(
    fn() => $p->showData('M01', 'Alya'),
    InvalidArgumentException::class,
    '__call dua argumen ditolak'
);

// Argumen lebih
fails(
    fn() => $p->showData('M01', 'Alya', 'TI', 'FST'),
    InvalidArgumentException::class,
    '__call empat argumen ditolak'
);

// Argumen bukan string
fails(
    fn() => $p->showData('M01', 'Alya', 123),
    InvalidArgumentException::class,
    '__call tipe data tidak valid'
);

// Argumen kosong
fails(
    fn() => $p->showData('M01', '', 'Informatika'),
    InvalidArgumentException::class,
    '__call string kosong ditolak'
);

// Nama method berbeda
fails(
    fn() => $p->unknown(),
    BadMethodCallException::class,
    '__call method tidak dikenal'
);