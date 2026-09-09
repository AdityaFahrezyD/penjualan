<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Pembatasan laju API dan cache barang/satuan

API menggunakan pembatasan request dan cache database Laravel yang sudah tersedia. Konfigurasi berikut dapat diatur melalui `.env` (lihat `.env.example`):

| Variabel | Default | Fungsi |
| --- | --- | --- |
| `API_LOGIN_IDENTITY_PER_MINUTE` | `5` | Batas login per kombinasi email yang dinormalisasi dan IP |
| `API_LOGIN_IP_PER_MINUTE` | `30` | Batas login gabungan per IP |
| `API_AUTHENTICATED_PER_MINUTE` | `120` | Batas gabungan semua endpoint API terautentikasi per pengguna, lintas token |
| `API_RESPONSE_CACHE_ENABLED` | `true` | Mengaktifkan cache respons barang/satuan |
| `API_RESPONSE_CACHE_TTL_SECONDS` | `60` | Masa berlaku payload cache dalam detik; nilai nol menonaktifkan cache |
| `CACHE_STORE` | `database` | Store bersama untuk cache respons dan penghitung request |

Semua percobaan login yang diterima limiter dihitung, termasuk login berhasil dan validasi gagal. Jendela pembatasan berlangsung 60 detik sejak request pertama. Batas minimal adalah satu request; cache yang dinonaktifkan tidak menonaktifkan rate limiter. Route web mempertahankan aturan sebelumnya.

Ketika kuota habis, API mengembalikan HTTP `429`:

```json
{"message":"Terlalu banyak permintaan. Silakan coba lagi beberapa saat."}
```

Frontend dapat membaca `Retry-After` (detik sebelum mencoba lagi), `X-RateLimit-Limit`, `X-RateLimit-Remaining`, dan `X-RateLimit-Reset` melalui CORS. Header reset/waktu tunggu tersedia pada respons pembatasan. Frontend sebaiknya menunggu waktu tersebut sebelum mengulang request, termasuk setelah klik berulang pada operasi mutasi.

Cache hanya berlaku untuk respons JSON HTTP 200 dari GET daftar/detail `/api/items` dan `/api/units`. Autentikasi, kuota, dan role selalu diperiksa sebelum cache. Payload dapat digunakan bersama oleh pengguna yang diizinkan karena kedua resource saat ini berisi data master yang sama bagi mereka. Path dan query parameter menjadi bagian kunci cache; urutan parameter objek dinormalisasi, sedangkan urutan nilai daftar dipertahankan. Jika endpoint nanti menjadi spesifik pengguna, pemisahan kunci cache juga harus diperbarui.

Perubahan model barang membatalkan cache barang setelah commit. Perubahan satuan membatalkan cache satuan dan barang karena relasi satuan ada pada respons barang. Penerimaan pesanan (`delivered`) membatalkan cache barang secara eksplisit setelah stok berhasil di-commit. Rollback membatalkan callback invalidasi. Pembatalan menggunakan versi resource baru; request lama tidak dapat mengisi ulang versi aktif dan kuota tetap utuh. Payload versi lama tidak lagi digunakan dan memiliki TTL terbatas. Pada store database, baris kedaluwarsa yang tidak dibaca lagi dapat tetap tersimpan; pembersihan berkala baris `cache` yang telah melewati `expiration` dapat dimasukkan ke pemeliharaan database bila volumenya meningkat.

Query update massal, perubahan langsung melalui SQL, atau proses lain yang melewati observer harus menginvalidasi `MasterDataCache` setelah commit. Jika tidak, perubahan baru terlihat setelah TTL. Pembelian/pembayaran dan logika transaksi tetap membaca database secara langsung.

### Penerapan dan verifikasi

- Tidak ada migrasi baru atau kebutuhan Redis. Pastikan migrasi `0001_01_01_000001_create_cache_table` sudah diterapkan dan tabel `cache` serta `cache_locks` tersedia pada database cache. Periksa menggunakan `php artisan migrate:status`; gunakan prosedur migrasi deployment biasa jika migrasi tersebut belum diterapkan.
- Setelah perubahan `.env`, bangun ulang konfigurasi menggunakan `php artisan config:cache` sesuai prosedur deployment. Semua instance aplikasi harus menggunakan cache database bersama agar kuota konsisten. Bila ada reverse proxy, konfigurasi trusted proxy harus sesuai infrastruktur agar IP limiter benar; jangan mempercayai header IP dari sumber sembarang.
- Cache respons dapat dimatikan menggunakan `API_RESPONSE_CACHE_ENABLED=false`. Menghapus seluruh cache juga mereset kuota; invalidasi fitur ini tidak menggunakan `Cache::flush()`.
- Jalankan `php vendor/bin/phpunit tests/Feature/Api/ApiRateLimitTest.php tests/Feature/Api/MasterDataCacheTest.php`, kemudian `php vendor/bin/phpunit tests/Feature/Api`. Tes memakai SQLite `:memory:`; suite cache juga menguji store database, bukan database aplikasi.
- Pantau jumlah/persentase respons 429 dan waktu respons endpoint dari access log atau monitoring hosting. Bandingkan latensi request pertama dan berulang pada beban yang sama; cache database masih melakukan query tabel cache sehingga percepatan tidak diasumsikan tanpa pengukuran. Sesuaikan kuota dan TTL berdasarkan hasilnya.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
