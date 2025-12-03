<?php
/**
 * File: api_proxy.php
 * Lokasi: D:\laragon\www\generator_ai\api_proxy.php
 * Fungsi: Entry point API. Menerima request klien, memanggil GeminiService, dan mengembalikan respons.
 * Diperbarui: 18 November 2025 (Refactoring: Menggunakan GeminiService)
 */

// --- SETUP AWAL ---
ob_start(); // Buffer output untuk menangkap error tak terduga
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Opsional: Jika akses lintas domain diperlukan
header('Access-Control-Allow-Methods: POST');

// Load Class Logic
require_once 'GeminiService.php';

// --- PENANGAN ERROR ---
function errorHandler($errno, $errstr, $errfile, $errline) {
    if (error_reporting() === 0) return false;
    ob_clean();
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => $errstr, 'location' => basename($errfile) . ":$errline"]);
    exit;
}
set_error_handler('errorHandler');

function fatalHandler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fatal Error', 'message' => $error['message']]);
    }
}
register_shutdown_function('fatalHandler');

// --- KONFIGURASI ---
$API_KEY = 'AIzaSyDpf9wKtYISbi79UdvCMsd_rMOXtNJErsg'; // Ganti dengan key asli Anda

// --- PROSES REQUEST ---
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Metode request harus POST.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception("Body request tidak valid atau kosong.");
    }

    $task = $input['task'] ?? '';
    $script = $input['script'] ?? '';
    
    // Opsi tambahan untuk Story Mode
    $options = [
        'script' => $script, // Untuk story_idea, script adalah ide utama
        'style' => $input['style'] ?? 'Cinematic',
        'characters' => $input['characters'] ?? ''
    ];

    if (empty($script)) {
        throw new Exception("Data input (naskah/ide) tidak boleh kosong.");
    }

    // Inisialisasi Service
    $gemini = new GeminiService($API_KEY);

    // Eksekusi Logika
    $jsonResponse = $gemini->generateContent($task, $script, $options);

    // Kirim Respons Sukses
    ob_end_clean();
    echo $jsonResponse;

} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode([
        'error' => 'Gagal Memproses Permintaan',
        'message' => $e->getMessage()
    ]);
}
?>