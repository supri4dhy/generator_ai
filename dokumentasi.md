Dokumentasi Aplikasi: Generator Rangkaian Adegan & Pemecah Naskah

Tanggal Dibuat: 17 November 2025
Lokasi Proyek: D:\laragon\www\generator_ai\ (Asumsi)
Lingkungan: Server lokal Laragon (PHP, Apache)

1. Tujuan Proyek

Aplikasi web ini dirancang untuk mengotomatisasi alur kerja pra-produksi video dari naskah mentah (terutama naskah berbahasa Jawa). Proyek ini dibagi menjadi dua alat (modul) utama:

Pemecah Naskah Cerita (pemecah_naskah.html): Menerima satu naskah panjang dan memecahnya menjadi beberapa naskah mentah (ekstrak) yang lebih kecil dan fokus pada sub-plot atau cerita mandiri.

Generator Rangkaian Adegan (generator_adegan.html): Menerima satu naskah (idealnya naskah yang sudah dipecah) dan menghasilkan tiga set data siap pakai:

Data SEO YouTube (Judul, Deskripsi, Tags, Hashtags).

Rangkaian Adegan Pendek (Narasi IDN + Prompt SORA/VEO EN) untuk video < 1 menit.

Rangkaian Adegan Panjang (Narasi IDN + Prompt SORA/VEO EN) untuk video 3+ menit.

2. Arsitektur Akhir (Model Hub-and-Spoke)

Proyek ini menggunakan arsitektur "Hub-and-Spoke" yang bersih dan terpisah:

Hub (index.html): Halaman menu utama yang bertindak sebagai "gerbang" untuk memilih alat.

Spokes (generator_adegan.html, pemecah_naskah.html): Dua aplikasi mandiri yang masing-masing fokus pada satu tugas spesifik.

Backend (api_proxy.php): Satu file backend PHP tunggal yang melayani permintaan dari kedua aplikasi "Spoke".

3. Daftar File Proyek

Berikut adalah rincian fungsional dari setiap file dalam proyek:

File 1: index.html (Dashboard Utama)

Lokasi: D:\laragon\www\generator_ai\index.html

Fungsi: Bertindak sebagai "Dashboard Utama" atau menu navigasi (Hub). Halaman ini tidak memiliki logika aplikasi.

Fitur Utama:

Menampilkan header proyek.

Menyediakan dua "kartu" tautan besar yang mengarahkan pengguna ke:

generator_adegan.html (Generator Rangkaian Adegan)

pemecah_naskah.html (Pemecah Naskah Cerita)

Teknologi: HTML, Tailwind CSS.

File 2: pemecah_naskah.html (Alat Pemecah)

Lokasi: D:\laragon\www\generator_ai\pemecah_naskah.html

Fungsi: Aplikasi mandiri untuk mengekstrak beberapa naskah cerita mandiri dari satu naskah panjang.

Logika JavaScript:

Saat tombol "Pecah Naskah" diklik, tombol berubah menjadi "Memproses..." (indikator loading).

Mengirim satu permintaan fetch() ke api_proxy.php dengan {"task": "stories", "script": "..."}.

Menampilkan hasil sebagai dropdown/akordeon. Judul cerita diklik untuk menampilkan naskah dan tombol aksi.

Fitur Utama:

Tombol "Salin Naskah" untuk setiap cerita.

Tombol "Simpan Cerita (.doc)" untuk setiap cerita.

Tampilan naskah menggunakan <pre> di dalam div dengan overflow-y-auto (bukan <textarea>) untuk tampilan yang rapi.

Tautan "Kembali ke Dashboard Utama".

Teknologi: HTML, Tailwind CSS, JavaScript (ES6).

File 3: generator_adegan.html (Alat Generator)

Lokasi: D:\laragon\www\generator_ai\generator_adegan.html

Fungsi: Aplikasi mandiri untuk menghasilkan data SEO dan rangkaian adegan dari satu naskah.

Logika JavaScript:

Saat tombol "Buat Rangkaian Adegan" diklik, UI direset dan menampilkan spinner di 3 tab.

Mengirim tiga permintaan fetch() secara paralel ke api_proxy.php:

{"task": "seo", "script": "..."}

{"task": "short", "script": "..."}

{"task": "long", "script": "..."}

Setiap tab (SEO, Versi Pendek, Versi Panjang) diperbarui secara individual saat datanya diterima.

Fitur Utama:

Tombol "Salin" untuk setiap bagian data (Judul, Deskripsi, Narasi, Prompt).

Tombol "Simpan Semua (.doc)" yang menggabungkan hasil dari ketiga tugas.

Tautan "Kembali ke Dashboard Utama".

Teknologi: HTML, Tailwind CSS, JavaScript (ES6).

File 4: api_proxy.php (Backend API Proxy)

Lokasi: D:\laragon\www\generator_ai\api_proxy.php

Fungsi: Single-entry-point backend yang aman untuk berkomunikasi dengan Google Gemini API. Menerima permintaan dari kedua file .html.

Logika Inti:

Membaca input task (seo, short, long, atau stories).

Menggunakan switch ($task) untuk memilih systemInstruction dan responseSchema yang tepat untuk Google Gemini.

Mengirim permintaan ke Google API menggunakan cURL.

Penanganan Error (Kritis):

Menggunakan ob_start(), register_shutdown_function('fatalErrorHandler'), dan set_error_handler('jsonErrorHandler') untuk menjamin bahwa skrip selalu mengembalikan respons JSON yang valid.

Ini secara spesifik mencegah error Unexpected token '<' di sisi klien (JavaScript) jika terjadi error fatal PHP (misal, error 500 atau error parsing).

Teknologi: PHP, cURL.

4. Alur Kerja Pengguna (Ideal)

Berikut adalah alur kerja langkah demi langkah yang direkomendasikan:

Buka index.html di browser.

Klik kartu "Buka Pemecah Naskah".

Di pemecah_naskah.html, tempel naskah panjang (misal, 5 halaman) ke dalam textarea input.

Klik "Pecah Naskah". Tunggu hingga selesai.

Aplikasi akan menampilkan beberapa (misal, 4) dropdown cerita mandiri.

Klik judul cerita yang diinginkan (misal, "Konflik Kasta"), lalu klik "Salin Naskah".

Klik "Kembali ke Dashboard Utama".

Klik kartu "Buka Generator Rangkaian Adegan".

Di generator_adegan.html, tempel naskah yang sudah disalin (naskah pendek hasil pecahan) ke textarea input.

Klik "Buat Rangkaian Adegan".

Tunggu hingga ketiga tab (SEO, Versi Pendek, Versi Panjang) selesai memuat.

Gunakan tombol "Salin" atau "Simpan Semua (.doc)" untuk mengambil hasilnya.

Ulangi langkah 7-12 untuk naskah pecahan lainnya.
