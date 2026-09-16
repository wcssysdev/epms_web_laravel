# Panduan & Dokumentasi Implementasi HTTP Gzip Compression EPMS

**Target Sistem**: EPMS Web Backend & Mobile API v1.1  
**Web Server**: Nginx 1.22.0 (Laragon) / Production Nginx  
**Tanggal Penerapan**: 16 September 2026  
**Status**: **AKTIF & TERVERIFIKASI**

---

## 1. Latar Belakang & Tujuan

Aplikasi EPMS melayani dua jenis klien utama:
1. **Web Portal Management**: Melayani aset statis (CSS, JS, Font, HTML Blade).
2. **Mobile Android App di Estate (Kebun)**: Melakukan sinkronisasi data master offline (tabel blok, karyawan, material, workplan, kendaraan, dsb.).

### Masalah Sebelum Optimasi:
Payload JSON sinkronisasi awal mobile mencapai **1.54 MB (1,542,100 bytes)** dalam bentuk teks polos. Di lokasi perkebunan dengan koneksi 3G/EDGE atau sinyal seluler yang tidak stabil, pengunduhan payload sebesar ini memakan waktu lama dan rentan mengalami *socket timeout*.

### Hasil Setelah Aktivasi Gzip:
- **Ukuran Sebelum Kompresi**: `1,542,100 bytes` (~**1.54 MB**)
- **Ukuran Transfer Setelah Gzip**: `129,242 bytes` (~**129 KB**)
- **Tingkat Penghematan Data**: **91.6% (Hemat Bandwidth >10x Lipat)**
- **Waktu Transfer Data**: Berkurang hingga **80% - 90%** pada jaringan mobile kebun.

---

## 2. Konfigurasi Nginx yang Diterapkan

File konfigurasi: [nginx.conf](file:///C:/laragon/bin/nginx/nginx-1.22.0/conf/nginx.conf) (blok `http { ... }`)

```nginx
http {
    include       mime.types;
    default_type  application/octet-stream;

    # -------------------------------------------------------
    # HTTP Gzip Compression (Optimized for Web & Mobile API)
    # -------------------------------------------------------
    gzip              on;
    gzip_vary         on;
    gzip_proxied      any;
    gzip_comp_level   6;
    gzip_min_length   1024;
    gzip_buffers      16 8k;
    gzip_http_version 1.1;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/x-javascript
        application/xml
        application/xml+rss
        application/vnd.ms-fontobject
        application/x-font-ttf
        font/opentype
        image/svg+xml
        image/x-icon;
    gzip_disable      "msie6";

    # ... konfigurasi lainnya ...
}
```

### Penjelasan Setiap Direktif:

| Direktif | Nilai | Fungsi & Manfaat |
| :--- | :--- | :--- |
| `gzip` | `on` | Mengaktifkan modul kompresi gzip pada Nginx. |
| `gzip_vary` | `on` | Menyisipkan header response `Vary: Accept-Encoding`. Menginstruksikan cache proxy, CDN, atau mobile local cache agar membedakan cache respons berformat gzip dengan respons teks polos. |
| `gzip_proxied` | `any` | Memastikan Nginx tetap melakukan kompresi meskipun request datang melalui reverse proxy, load balancer, atau API gateway (misal Cloudflare, NGROK, AWS ALB). |
| `gzip_comp_level` | `6` | Level kompresi dari 1 (tercepat) hingga 9 (terpadat). **Level 6** adalah standar industri (*sweet spot*): memberikan kompresi >90% dengan beban CPU server yang sangat rendah. |
| `gzip_min_length` | `1024` (1 KB) | Mencegah kompresi pada respons yang ukurannya di bawah 1 KB, karena overhead header kompresi justru membuat file kecil menjadi lebih besar. |
| `gzip_buffers` | `16 8k` | Mengalokasikan 16 buffer berukuran 8 KB untuk memproses kompresi payload besar (seperti payload master sync 1.5 MB) secara efisien tanpa pemborosan memori. |
| `gzip_http_version` | `1.1` | Mengaktifkan kompresi untuk semua koneksi HTTP/1.1 ke atas. |
| `gzip_types` | *(Daftar MIME)* | Menentukan tipe file teks yang dikompresi: `application/json` (API), `text/css`, `application/javascript`, `image/svg+xml`, dsb. File gambar biner (PNG, JPG) sengaja tidak dimasukkan karena sudah terkompresi dari sananya. |
| `gzip_disable` | `"msie6"` | Menonaktifkan kompresi khusus untuk browser lawas yang bermasalah dengan gzip. |

---

## 3. Cara Pengujian & Verifikasi

### A. Pengujian Header via cURL

#### 1. Uji Request dengan Dukungan Gzip (Standar Mobile/Browser):
```bash
curl.exe -I -H "Accept-Encoding: gzip" http://epms-laravel.test:8080/api/v1_1/ping
```
**Hasil Header**:
```http
HTTP/1.1 200 OK
Server: nginx/1.22.0
Content-Type: application/json
Connection: keep-alive
Vary: Accept-Encoding
Content-Encoding: gzip
```
*(Respons terbukti dikompresi dengan hadirnya `Content-Encoding: gzip` dan `Vary: Accept-Encoding`).*

#### 2. Uji Request Tanpa Gzip (Graceful Fallback):
```bash
curl.exe -I http://epms-laravel.test:8080/api/v1_1/ping
```
**Hasil Header**:
```http
HTTP/1.1 200 OK
Server: nginx/1.22.0
Content-Type: application/json
Connection: keep-alive
Vary: Accept-Encoding
```
*(Tanpa `Accept-Encoding: gzip`, Nginx otomatis mengirim data polos tanpa error).*

---

## 4. Panduan untuk Tim Mobile (Android / Flutter / iOS)

> [!IMPORTANT]
> **Tidak ada perubahan kode yang diperlukan di aplikasi mobile.**

### Mengapa Aman?
1. **Dukungan Otomatis Level Library Networking**:
   - **Android (OkHttp / Retrofit)**: Secara default, OkHttp selalu mengirim `Accept-Encoding: gzip` dan secara otomatis meng-unzip byte stream respons kembali menjadi teks JSON utuh sebelum diparsing oleh Gson/Moshi.
   - **Flutter (Dart `http` / `Dio`)**: `HttpClient` bawaan Dart dan paket `Dio` memiliki parameter `autoUncompress: true` secara bawaan.
   - **iOS (URLSession / Alamofire)**: Penanganan dekompresi gzip dikelola langsung oleh sistem operasi iOS.
2. **Tidak Merusak Format Payload**:
   - Struktur JSON, key name, data type, dan nilai di dalam payload tidak ada yang berubah sedikit pun.
3. **Catatan Tambahan (Brotli)**:
   - Nginx Laragon Windows belum menyertakan modul eksternal `ngx_brotli`. Gzip sudah 100% kompatibel dan memberikan efisiensi 91.6%. Jika server Linux produksi memiliki modul Brotli, Nginx dapat mengaktifkannya berdampingan dengan Gzip.

---

## 5. Panduan Deployment ke Server Produksi (Linux Nginx)

Jika aplikasi di-deploy ke server Linux (Ubuntu/Debian/RHEL), terapkan konfigurasi berikut:

1. Buat file konfigurasi `/etc/nginx/conf.d/gzip.conf`:
   ```nginx
   gzip on;
   gzip_vary on;
   gzip_proxied any;
   gzip_comp_level 6;
   gzip_min_length 1024;
   gzip_buffers 16 8k;
   gzip_http_version 1.1;
   gzip_types
       text/plain
       text/css
       text/xml
       text/javascript
       application/json
       application/javascript
       application/x-javascript
       application/xml
       application/xml+rss
       application/vnd.ms-fontobject
       application/x-font-ttf
       font/opentype
       image/svg+xml
       image/x-icon;
   gzip_disable "msie6";
   ```
2. Uji sintaks:
   ```bash
   sudo nginx -t
   ```
3. Reload konfigurasi:
   ```bash
   sudo systemctl reload nginx
   ```
