<?php
/**
 * File: GeminiService.php
 * Lokasi: D:\laragon\www\generator_ai\GeminiService.php
 * Fungsi: Class untuk menangani logika komunikasi dengan Google Gemini API.
 * Dibuat: 18 November 2025
 */

class GeminiService {
    private $apiKey;
    private $apiUrl;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key={$this->apiKey}";
    }

    /**
     * Memproses permintaan generate konten
     */
    public function generateContent($task, $script, $options = []) {
        // 1. Validasi & Siapkan Instruksi
        list($systemInstruction, $responseSchema, $temperature) = $this->getTaskConfig($task, $options);

        // 2. Susun Payload
        $payload = [
            'contents' => [['parts' => [['text' => $script]]]],
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $responseSchema,
                'temperature' => $temperature,
                'topP' => 1.0,
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
            ]
        ];

        // 3. Kirim Request (cURL)
        return $this->sendRequest($payload);
    }

    /**
     * Mengembalikan konfigurasi berdasarkan tugas (System Instruction, Schema, Temp)
     */
    private function getTaskConfig($task, $options) {
        $systemInstruction = "";
        $responseSchema = [];
        $temperature = 0.7; // Default

        // Schema Adegan (Reusable)
        $sceneItemSchema = [
            'type' => 'OBJECT',
            'properties' => [
                'scene' => ['type' => 'STRING', 'description' => 'Nomor Adegan'],
                'title' => ['type' => 'STRING'],
                'dialog_narasi' => ['type' => 'STRING', 'description' => 'Dialog/Narasi (ID)'],
                'sora_prompt' => ['type' => 'STRING', 'description' => 'Visual Prompt (EN)'],
                'veo_prompt' => ['type' => 'STRING', 'description' => 'Visual Prompt (EN)']
            ],
            'required' => ['scene', 'title', 'dialog_narasi', 'sora_prompt', 'veo_prompt']
        ];

        switch ($task) {
            case 'seo':
                $systemInstruction = "Anda adalah spesialis SEO YouTube. Buat title, description, tags, hashtags. Sertakan 'Babat Tanah Jawa'.";
                $responseSchema = [
                    'type' => 'OBJECT', 
                    'properties' => [
                        'title' => ['type' => 'STRING'], 
                        'description' => ['type' => 'STRING'], 
                        'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']], 
                        'hashtags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]
                    ], 
                    'required' => ['title', 'description', 'tags', 'hashtags']
                ];
                break;

            case 'short':
                $systemInstruction = "Anda editor video. Buat 6-8 adegan pendek (< 1 menit).
                ATURAN PROMPT:
                Prompt visual (`sora_prompt` & `veo_prompt`) WAJIB format gabungan: `[Visual Description EN] -- Narator/Dialog: \"[Teks Bahasa Indonesia]\"`
                Dialog/Narasi harus Bahasa Indonesia.";
                $responseSchema = ['type' => 'OBJECT', 'properties' => ['scenes' => ['type' => 'ARRAY', 'items' => $sceneItemSchema]], 'required' => ['scenes']];
                break;

            case 'long':
                $systemInstruction = "Anda editor video. Buat 18-25 adegan panjang (3+ menit).
                ATURAN PROMPT:
                Prompt visual (`sora_prompt` & `veo_prompt`) WAJIB format gabungan: `[Visual Description EN] -- Narator/Dialog: \"[Teks Bahasa Indonesia]\"`
                Dialog/Narasi harus Bahasa Indonesia.";
                $responseSchema = ['type' => 'OBJECT', 'properties' => ['scenes' => ['type' => 'ARRAY', 'items' => $sceneItemSchema]], 'required' => ['scenes']];
                break;
            
            case 'stories':
                $systemInstruction = "Anda editor naskah. Pecah naskah panjang jadi 3-5 cerita mandiri.";
                $responseSchema = ['type' => 'OBJECT', 'properties' => ['stories' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['title' => ['type' => 'STRING'], 'script' => ['type' => 'STRING']], 'required' => ['title', 'script']]]], 'required' => ['stories']];
                break;

            case 'story_idea':
                $style = $options['style'] ?? 'Cinematic';
                $charList = $options['characters'] ?? '';
                $temperature = 0.85; // Lebih kreatif

                $systemInstruction = "Penulis Skenario & Sutradara AI.
                Input: Ide='$options[script]', Style='$style', Char='$charList'.
                Tugas: Storyboard Video Detail.
                ATURAN:
                1. Dialog hidup (Bahasa Indonesia).
                2. Prompt Visual (SORA/VEO) format: `[Visual Description EN] -- Audio/Dialog: \"[Teks Bahasa Indonesia]\"`
                3. Konsistensi karakter.";
                
                $responseSchema = [
                    'type' => 'OBJECT',
                    'properties' => [
                        'judul' => ['type' => 'STRING'],
                        'sinopsis' => ['type' => 'STRING'],
                        'karakter_detail' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => ['nama' => ['type' => 'STRING'], 'deskripsi_visual' => ['type' => 'STRING']], 'required' => ['nama', 'deskripsi_visual']]],
                        'scenes' => ['type' => 'ARRAY', 'items' => $sceneItemSchema]
                    ],
                    'required' => ['judul', 'sinopsis', 'karakter_detail', 'scenes']
                ];
                break;
            
            default:
                throw new Exception("Tugas '$task' tidak dikenali oleh sistem.");
        }

        return [$systemInstruction, $responseSchema, $temperature];
    }

    /**
     * Mengirim permintaan cURL ke Google API
     */
    private function sendRequest($payload) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Koneksi Gagal (cURL): $curlError");
        }

        if ($httpcode >= 400) {
            $errorBody = json_decode($response, true);
            $msg = $errorBody['error']['message'] ?? "Terjadi kesalahan pada server Google.";
            throw new Exception("Google API Error ($httpcode): $msg");
        }

        $data = json_decode($response, true);
        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$content) {
            throw new Exception("Respons kosong atau format tidak sesuai dari Google.");
        }

        return $content; // Kembalikan string JSON mentah
    }
}
?>