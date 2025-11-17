<?php

/**
 * File: api_proxy.php
 * Lokasi: D:\server\generator_ai\api_proxy.php
 * Fungsi: Backend proxy untuk menangani 3 tugas (seo, short, long) secara terpisah
 * ke Google Gemini API. Dilengkapi penanganan error yang kuat untuk 
 * selalu mengembalikan JSON dan menghindari error 'Unexpected token <'.
 * Diperbarui: 17 November 2025 (Pengetatan instruksi 'short')
 */

// --------------------------------------------------------------------------
// KONFIGURASI API
// --------------------------------------------------------------------------

// !!! PENTING: Ganti dengan API Key Google AI Anda yang sebenarnya
define('GEMINI_API_KEY', 'AIzaSyDpf9wKtYISbi79UdvCMsd_rMOXtNJErsg');

// Model API yang digunakan
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=' . GEMINI_API_KEY);


// --------------------------------------------------------------------------
// PENANGANAN ERROR PHP (KRITIS)
// --------------------------------------------------------------------------
// Tujuan: Memastikan skrip *selalu* mengembalikan JSON yang valid, bahkan saat
//         terjadi error fatal PHP (Parse error, etc.).

// 1. Mulai output buffering
ob_start();

// 2. Atur header default ke JSON
header('Content-Type: application/json');

/**
 * Jaring Pengaman (Fatal Error Handler)
 * Menangkap E_ERROR, E_PARSE, dll. yang tidak bisa ditangkap set_error_handler
 */
function fatalErrorHandler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Hapus buffer output yang mungkin berisi HTML error PHP
        ob_end_clean(); 
        
        // Kirim respons JSON error yang bersih
        echo json_encode([
            'error' => 'Terjadi error fatal pada server.',
            'php_error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit; // Hentikan eksekusi
    }
}
register_shutdown_function('fatalErrorHandler');

/**
 * Penangan Error Non-Fatal
 * Mengubah Warning/Notice PHP menjadi JSON error, mencegahnya merusak output JSON
 */
function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // Hapus buffer output
    ob_end_clean(); 
    
    // Kirim JSON error
    echo json_encode([
        'error' => 'Terjadi error non-fatal pada server.',
        'php_warning' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit; // Hentikan eksekusi
}
set_error_handler('jsonErrorHandler');

// --------------------------------------------------------------------------
// LOGIKA UTAMA API
// --------------------------------------------------------------------------

try {
    // 1. Ambil input JSON dari Klien
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Input JSON tidak valid.');
    }

    $task = $input['task'] ?? null;
    $script = $input['script'] ?? '';

    if (empty($script)) {
        throw new Exception('Naskah (script) tidak boleh kosong.');
    }
    if (empty($task) || !in_array($task, ['seo', 'short', 'long', 'stories'])) { // Ditambahkan 'stories'
        throw new Exception('Tugas (task) tidak valid. Harus "seo", "short", "long", atau "stories".');
    }

    // 2. Siapkan System Instruction dan Skema JSON berdasarkan Tugas
    $systemInstruction = '';
    $responseSchema = [];
    $userPrompt = "Berikut adalah naskah video mentah:\n\n---\n" . $script . "\n---\n\nHasilkan data yang diminta.";

    switch ($task) {
        // --- TUGAS: SEO ---
        case 'seo':
            $systemInstruction = "Anda adalah pakar SEO YouTube. Hasilkan satu Judul yang **menarik dan memikat** (maks 100 karakter), satu Deskripsi (maks 5000 karakter, gunakan paragraf), daftar Tags (kata kunci relevan), dan daftar Hashtags (termasuk #) berdasarkan naskah yang diberikan. **Pastikan untuk memasukkan 'Babat Tanah Jawa' sebagai salah satu tag DAN hashtag (#BabatTanahJawa), karena ini adalah sumber cerita.** Jawab HANYA dalam format JSON yang diminta.";
            $responseSchema = [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING', 'description' => 'Judul video YouTube yang dioptimalkan, maks 100 karakter.'],
                    'description' => ['type' => 'STRING', 'description' => 'Deskripsi YouTube yang dioptimalkan, maks 5000 karakter.'],
                    'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Daftar kata kunci SEO (tags).'],
                    'hashtags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Daftar hashtags (diawali #).']
                ]
            ];
            break;

        // --- TUGAS: VERSI PENDEK (DIPERBARUI) ---
        case 'short':
            $systemInstruction = "Anda adalah editor video AI. Pecah naskah menjadi rangkaian adegan (scene-by-scene) untuk video pendek (total 6-8 adegan, durasi di bawah 1 menit). **PERHATIAN: Naskah mentah kemungkinan dalam Bahasa Jawa.** Untuk setiap adegan, pahami naskah Jawa tersebut dan **tulis narasi BARU dalam Bahasa Indonesia** yang **SANGAT SINGKAT DAN PADAT (1-2 kalimat)**. **JANGAN HANYA MENYALIN naskah aslinya.** Pastikan total gabungan narasi tetap di bawah 1 menit. Berikan juga dua prompt visual (Bahasa Inggris) untuk SORA dan VEO **yang secara langsung mendeskripsikan visual dari narasi singkat tersebut.** Jawab HANYA dalam format JSON yang diminta.";
            $responseSchema = [
                'type' => 'OBJECT',
                'properties' => [
                    'scenes' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'scene' => ['type' => 'STRING', 'description' => 'Nomor adegan, cth: "1" atau "1A"'],
                                'narasi' => ['type' => 'STRING', 'description' => 'Teks narasi SANGAT SINGKAT (1-2 kalimat) untuk adegan ini (Bahasa Indonesia).'],
                                'sora_prompt' => ['type' => 'STRING', 'description' => 'Prompt visual mendetail untuk SORA (Bahasa Inggris).'],
                                'veo_prompt' => ['type' => 'STRING', 'description' => 'Prompt visual mendetail untuk VEO (Bahasa Inggris).']
                            ]
                        ]
                    ]
                ]
            ];
            break;

        // --- TUGAS: VERSI PANJANG ---
        case 'long':
            $systemInstruction = "Anda adalah sutradara AI. Kembangkan naskah menjadi rangkaian adegan (scene-by-scene) mendetail untuk video panjang (total 18-25 adegan, durasi 3+ menit). **PERHATIAN: Naskah mentah kemungkinan dalam Bahasa Jawa.** Jaga konsistensi karakter dan lokasi. Untuk setiap adegan, pahami naskah Jawa tersebut dan **tulis narasi BARU dalam Bahasa Indonesia.** **JANGAN HANYA MENYALIN naskah aslinya.** **PENTING: Dua prompt visual (Bahasa Inggris) untuk SORA dan VEO harus merupakan deskripsi visual yang setia dan mendetail dari apa yang terjadi atau dijelaskan dalam narasi (Bahasa Indonesia) tersebut.** Jawab HANYA dalam format JSON yang diminta.";
            $responseSchema = [ // Skema sama dengan versi pendek, hanya instruksi yang beda
                'type' => 'OBJECT',
                'properties' => [
                    'scenes' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'scene' => ['type' => 'STRING', 'description' => 'Nomor adegan, cth: "1" atau "1A"'],
                                'narasi' => ['type' => 'STRING', 'description' => 'Teks narasi untuk adegan ini (Bahasa Indonesia).'],
                                'sora_prompt' => ['type' => 'STRING', 'description' => 'Prompt visual mendetail untuk SORA (Bahasa Inggris).'],
                                'veo_prompt' => ['type' => 'STRING', 'description' => 'Prompt visual mendetail untuk VEO (Bahasa Inggris).']
                            ]
                        ]
                    ]
                ]
            ];
            break;
        
        // --- TUGAS BARU: CERITA MANDIRI (DIPERBARUI UNTUK EKSTRAKSI) ---
        case 'stories':
            $systemInstruction = "Anda adalah seorang editor naskah (script editor). Naskah mentah yang panjang (kemungkinan besar dalam Bahasa Jawa) ini berisi beberapa cerita/insiden. Tugas Anda adalah mengidentifikasi 3-5 'cerita mandiri' (sub-plot) yang dapat berdiri sendiri. 
**ATURAN KETAT:**
1.  Anda harus mengidentifikasi **BEBERAPA (3-5) cerita BERBEDA**.
2.  **JANGAN** memasukkan seluruh naskah ke dalam satu cerita. Setiap 'script' yang diekstrak harus merupakan **bagian KECIL** dari naskah utama.
3.  Setiap cerita harus unik dan tidak tumpang tindih secara signifikan.

Untuk setiap cerita mandiri, berikan:
1.  `title` (Judul yang menarik dalam Bahasa Indonesia).
2.  `script` (Teks naskah ASLI yang relevan untuk cerita kecil tersebut, diekstrak dari naskah utama).

**PENTING: JANGAN meringkas atau menerjemahkan naskah.** Kembalikan bagian naskah asli yang relevan untuk cerita itu. Jawab HANYA dalam format JSON yang diminta.";
            $responseSchema = [
                'type' => 'OBJECT',
                'properties' => [
                    'stories' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => ['type' => 'STRING', 'description' => 'Judul yang menarik untuk cerita mandiri ini (Bahasa Indonesia).'],
                                'script' => ['type' => 'STRING', 'description' => 'Teks naskah ASLI yang lengkap untuk cerita ini, diekstrak dari naskah utama.']
                            ]
                        ]
                    ]
                ]
            ];
            break;
    }

    // 3. Siapkan Payload untuk Google Gemini
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => $responseSchema,
            'temperature' => 0.7,
        ]
    ];

    // 4. Kirim Permintaan cURL ke Google API
    $ch = curl_init(GEMINI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout 2 menit (tugas panjang mungkin lama)

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 5. Tangani Respons dari Google
    
    // Menangani error cURL atau non-200 (misal: 400, 429, 500 dari Google)
    if ($response === false || $httpCode != 200) {
        // Ini adalah penanganan error kritis:
        // Jika Google mengembalikan HTML (misal error 500), $response akan berisi HTML.
        // Kita *tidak* boleh meng-echo $response. Kita buat JSON error kita sendiri.
        throw new Exception(
            "Gagal berkomunikasi dengan Google API. HTTP Status: $httpCode. cURL Error: $curlError. Respons (mungkin HTML): " . substr($response, 0, 200) . "..."
        );
    }

    // Google mengembalikan 200, saatnya mengurai JSON wrapper-nya
    $responseData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Gagal mengurai JSON wrapper dari Google.');
    }

    // Cek jika ada error di dalam JSON wrapper (misal: prompt diblokir)
    if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $errorDetail = $responseData['promptFeedback']['blockReason'] ?? 'Struktur respons tidak dikenal';
        throw new Exception("Google API mengembalikan error: " . $errorDetail);
    }

    // 6. Ekstrak JSON *Inner* dan Kirim ke Klien
    // Ini adalah JSON yang kita minta (hasil dari skema)
    $geminiJsonOutput = $responseData['candidates'][0]['content']['parts'][0]['text'];

    // Hapus buffer output sebelum mengirim respons akhir
    ob_end_clean();
    
    // Set header lagi (untuk jaga-jaga jika ter-reset oleh error)
    header('Content-Type: application/json');
    
    // Langsung echo string JSON inner. JANGAN json_encode() lagi!
    echo $geminiJsonOutput;
    exit;

} catch (Exception $e) {
    // Menangkap semua 'throw new Exception'
    ob_end_clean(); // Hapus buffer
    header('Content-Type: application/json'); // Set header (lagi)
    http_response_code(500); // Set status error
    echo json_encode([
        'error' => 'Terjadi pengecualian (Exception) pada server.',
        'message' => $e->getMessage()
    ]);
    exit;
}

// 7. Akhiri output buffering (jika skrip sampai sini tanpa exit)
ob_end_flush();

?>