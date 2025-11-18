<?php
/**
 * File: api_proxy.php
 * Lokasi: D:\laragon\www\generator_ai\api_proxy.php
 * Fungsi: Backend proxy aman untuk Google Gemini API. 
 * Diperbarui: Menambahkan task 'story_idea' untuk Generator Cerita Komik.
 */

// --- FUNGSI PENANGANAN ERROR (Kritis) ---
ob_start();

function jsonErrorHandler($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) return false;
    ob_clean(); 
    http_response_code(500);
    echo json_encode(['error' => 'Error Internal Server (PHP)', 'message' => $errstr, 'file' => $errfile, 'line' => $errline]);
    exit;
}
set_error_handler('jsonErrorHandler');

function fatalErrorHandler() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        ob_clean(); 
        http_response_code(500); 
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error Internal Server (PHP Fatal)', 'message' => $error['message']]);
    } else {
        ob_end_flush();
    }
}
register_shutdown_function('fatalErrorHandler');

// --- KONFIGURASI API ---
$GEMINI_API_KEY = 'AIzaSyDpf9wKtYISbi79UdvCMsd_rMOXtNJErsg'; 
$GEMINI_API_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key={$GEMINI_API_KEY}";

header('Content-Type: application/json');

try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true);

    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Input JSON tidak valid.");

    $task = $input['task'] ?? '';
    $script = $input['script'] ?? ''; // Untuk 'story_idea', ini adalah IDE CERITA

    if (empty($script)) throw new Exception("Input tidak boleh kosong.");

    // Daftar task yang valid diperbarui
    $validTasks = ['seo', 'short', 'long', 'stories', 'story_idea'];
    if (empty($task) || !in_array($task, $validTasks)) {
        throw new Exception("Tugas (task) tidak valid.");
    }

    $systemInstruction = "";
    $responseSchema = [];

    switch ($task) {
        // ... (Case SEO, SHORT, LONG, STORIES tetap sama - disembunyikan agar ringkas) ...
        case 'seo':
            $systemInstruction = "Anda adalah spesialis SEO YouTube. Buat title, description, tags, hashtags. Sertakan 'Babat Tanah Jawa'.";
            $responseSchema = ['type' => 'OBJECT', 'properties' => ['title' => ['type' => 'STRING'], 'description' => ['type' => 'STRING'], 'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']], 'hashtags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]], 'required' => ['title', 'description', 'tags', 'hashtags']];
            break;
        case 'short':
            $systemInstruction = "Anda editor video. Buat 6-8 adegan pendek (<1 menit). Narasi (ID), Prompt (EN).";
            $responseSchema = ['type' => 'OBJECT', 'properties' => ['scenes' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['scene' => ['type' => 'STRING'], 'title' => ['type' => 'STRING'], 'narasi' => ['type' => 'STRING'], 'sora_prompt' => ['type' => 'STRING'], 'veo_prompt' => ['type' => 'STRING']], 'required' => ['scene', 'title', 'narasi', 'sora_prompt', 'veo_prompt']]]], 'required' => ['scenes']];
            break;
        case 'long':
            $systemInstruction = "Anda editor video. Buat 18-25 adegan panjang (3+ menit). Narasi (ID), Prompt (EN).";
            $responseSchema = ['type' => 'OBJECT', 'properties' => ['scenes' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['scene' => ['type' => 'STRING'], 'title' => ['type' => 'STRING'], 'narasi' => ['type' => 'STRING'], 'sora_prompt' => ['type' => 'STRING'], 'veo_prompt' => ['type' => 'STRING']], 'required' => ['scene', 'title', 'narasi', 'sora_prompt', 'veo_prompt']]]], 'required' => ['scenes']];
            break;
        case 'stories':
            $systemInstruction = "Anda editor naskah. Pecah naskah panjang jadi 3-5 cerita mandiri.";
            $responseSchema = ['type' => 'OBJECT', 'properties' => ['stories' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['title' => ['type' => 'STRING'], 'script' => ['type' => 'STRING']], 'required' => ['title', 'script']]]], 'required' => ['stories']];
            break;

        // --- TUGAS BARU: GENERATOR CERITA KOMIK ---
        case 'story_idea':
            $systemInstruction = "Anda adalah Penulis Komik Kreatif. Pengguna akan memberikan sebuah IDE atau PREMIS cerita.
Tugas Anda:
1. Kembangkan ide menjadi cerita seru bergaya komik/animasi.
2. Ciptakan karakter unik dengan ciri khas visual yang kuat.
3. Tulis dialog yang hidup dan natural (Bahasa Indonesia).
4. Pecah cerita menjadi 'Panel' atau 'Adegan' berurutan.

Output JSON harus berisi:
- `judul`: Judul Komik yang menarik.
- `sinopsis`: Ringkasan cerita yang seru.
- `karakter`: Daftar tokoh beserta deskripsi visual singkatnya.
- `scenes`: Rangkaian panel/adegan. Untuk setiap scene:
    - `title`: Judul panel/adegan (misal: 'Panel 1: Pertemuan').
    - `dialog_narasi`: Teks dialog antar tokoh atau narasi kotak (caption). Format naskah komik. (Bahasa Indonesia).
    - `sora_prompt`: Deskripsi visual gaya 'Cinematic Comic Book' untuk SORA. Jelaskan angle kamera, pencahayaan dramatis, dan ekspresi karakter. (Bahasa Inggris).
    - `veo_prompt`: Deskripsi visual gaya 'Animated Storyboard' untuk VEO. Fokus pada aksi dan gerakan. (Bahasa Inggris).";
            
            $responseSchema = [
                'type' => 'OBJECT',
                'properties' => [
                    'judul' => ['type' => 'STRING'],
                    'sinopsis' => ['type' => 'STRING'],
                    'karakter' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'scenes' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'scene' => ['type' => 'STRING', 'description' => 'Nomor Panel/Adegan'],
                                'title' => ['type' => 'STRING'],
                                'dialog_narasi' => ['type' => 'STRING', 'description' => 'Dialog/Caption (ID)'],
                                'sora_prompt' => ['type' => 'STRING', 'description' => 'Visual Prompt (EN)'],
                                'veo_prompt' => ['type' => 'STRING', 'description' => 'Visual Prompt (EN)']
                            ],
                            'required' => ['scene', 'title', 'dialog_narasi', 'sora_prompt', 'veo_prompt']
                        ]
                    ]
                ],
                'required' => ['judul', 'sinopsis', 'karakter', 'scenes']
            ];
            break;
    }

    $payload = [
        'contents' => [['parts' => [['text' => $script]]]],
        'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => $responseSchema,
            'temperature' => 0.85, // Lebih kreatif untuk cerita
            'topP' => 1.0,
        ],
        'safetySettings' => [
            ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
            ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $GEMINI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) throw new Exception("Error cURL: {$curlError}");
    
    if ($httpcode >= 400) {
        $errorResponse = json_decode($response, true);
        $msg = $errorResponse['error']['message'] ?? substr(strip_tags($response), 0, 200);
        throw new Exception("Error Google API (HTTP {$httpcode}): " . $msg);
    }

    $responseData = json_decode($response, true);
    $jsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    
    if ($jsonString === null) throw new Exception("Format respons Google tidak terduga.");

    header('Content-Type: application/json');
    echo $jsonString;

} catch (Exception $e) {
    ob_clean(); 
    http_response_code(400); 
    echo json_encode(['error' => 'Gagal memproses', 'message' => $e->getMessage()]);
}
?>