<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class AiIntegrationController extends Controller
{
    public function index(): View
    {
        $agents = $this->agents();
        $sdkPrompts = $this->sdkPrompts();
        $firstPrompts = $this->firstPrompts();
        $cliSnippets = $this->cliSnippets();

        return view('ai.index', compact('agents', 'sdkPrompts', 'firstPrompts', 'cliSnippets'));
    }

    /**
     * Mengunduh berkas master spesifikasi AI Gateway dalam format Markdown (.md).
     */
    public function downloadMasterMd(): Response
    {
        $agents = $this->agents();

        $md = "# Pesanvo Gateway - Panduan Integrasi AI Agent & Developer\n\n";
        $md .= "> Spesifikasi teknis resmi Pesanvo Gateway (https://wa.pesanvo.id) untuk AI Coding Assistants dan pengembang sistem.\n\n";
        $md .= "---\n\n";
        $md .= "## 1. Ikhtisar & Arsitektur Gateway\n\n";
        $md .= "- **Base URL:** `https://wa.pesanvo.id/api/v1`\n";
        $md .= "- **Protokol:** REST API murni berbasis `application/json`\n";
        $md .= "- **Header Autentikasi:** `X-Api-Key: <API_KEY_ANDA>`\n";
        $md .= "- **Endpoint Status:** `GET https://wa.pesanvo.id/status.json`\n";
        $md .= "- **Dokumentasi Web:** https://wa.pesanvo.id/docs\n\n";
        $md .= "---\n\n";
        $md .= "## 2. Konfigurasi Variabel Lingkungan (.env)\n\n";
        $md .= "Simpan kredensial gateway di berkas `.env` backend Anda:\n\n";
        $md .= "```env\n";
        $md .= "WA_GATEWAY_URL=https://wa.pesanvo.id\n";
        $md .= "WA_GATEWAY_KEY=vwa_live_xxxxxxxxxxxxxxxx\n";
        $md .= "```\n\n";
        $md .= "---\n\n";
        $md .= "## 3. Spesifikasi Endpoint REST API\n\n";
        $md .= "### A. Kirim Pesan Teks\n";
        $md .= "- **Method:** `POST /api/v1/messages/text`\n";
        $md .= "- **Header:**\n";
        $md .= "  - `Content-Type: application/json`\n";
        $md .= "  - `X-Api-Key: vwa_live_xxxxxxxxxxxxxxxx`\n";
        $md .= "- **Payload JSON:**\n";
        $md .= "```json\n";
        $md .= "{\n";
        $md .= "  \"to\": \"081234567890\",\n";
        $md .= "  \"message\": \"Halo! Pesan transaksional dari sistem.\"\n";
        $md .= "}\n";
        $md .= "```\n";
        $md .= "- **Format Respon Sukses (200 OK):**\n";
        $md .= "```json\n";
        $md .= "{\n";
        $md .= "  \"success\": true,\n";
        $md .= "  \"data\": {\n";
        $md .= "    \"id\": \"msg_1029\",\n";
        $md .= "    \"status\": \"queued\"\n";
        $md .= "  }\n";
        $md .= "}\n";
        $md .= "```\n\n";
        $md .= "### B. Kirim Dokumen & Media (PDF, Gambar, dsb)\n";
        $md .= "- **Method:** `POST /api/v1/messages/media`\n";
        $md .= "- **Header:** `Content-Type: multipart/form-data`, `X-Api-Key: vwa_live_xxxxxxxxxxxxxxxx`\n";
        $md .= "- **Parameter Form:**\n";
        $md .= "  - `to`: Nomor tujuan (format 08... atau 62...)\n";
        $md .= "  - `caption`: Teks pesan pendamping (opsional)\n";
        $md .= "  - `type`: `document` | `image` | `audio` | `video`\n";
        $md .= "  - `file`: Berkas fisik yang dikirim\n\n";
        $md .= "### C. Pengiriman Massal (Bulk Messages)\n";
        $md .= "- **Method:** `POST /api/v1/messages/bulk`\n";
        $md .= "- **Header:** `Content-Type: application/json`, `X-Api-Key: vwa_live_xxxxxxxxxxxxxxxx`\n";
        $md .= "- **Catatan:** Server otomatis mengelola jeda dan antrean anti-blokir WhatsApp.\n\n";
        $md .= "---\n\n";
        $md .= "## 4. Instruksi & Prompt untuk AI Coding Assistant\n\n";

        foreach ($agents as $agent) {
            $md .= '### '.$agent['name'].' ('.$agent['tool'].")\n";
            $md .= '- **Target Berkas:** `'.$agent['file']."`\n";
            $md .= '- **Deskripsi:** '.$agent['desc']."\n\n";
            $md .= "```text\n".$agent['prompt']."\n```\n\n";
        }

        $md .= "---\n\n";
        $md .= "## 5. Standar Penanganan Galat (Error Handling)\n\n";
        $md .= "- **401 Unauthorized:** Kunci API belum diisi atau salah. Periksa header `X-Api-Key`.\n";
        $md .= "- **422 Unprocessable Entity:** Format nomor tujuan tidak valid atau payload tidak lengkap.\n";
        $md .= "- **503 Service Unavailable:** Sesi WhatsApp sedang reconnect. Tangani dengan retry aman.\n";

        return response($md, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="PESANVO-AI.md"',
        ]);
    }

    /**
     * Mengunduh berkas konfigurasi / prompt spesifik agent (.md, .json, .rules).
     */
    public function downloadAgentFile(string $agent): Response
    {
        $agents = $this->agents();

        if (! isset($agents[$agent])) {
            abort(404, 'AI Agent tidak ditemukan.');
        }

        $agentData = $agents[$agent];
        $filename = $agentData['file'];
        $prompt = $agentData['prompt'];

        $content = match ($agent) {
            'claude' => "# Claude Code - WhatsApp Gateway Instructions\n\n{$prompt}\n",
            'cursor' => "{$prompt}\n",
            'hermes' => "---\nname: pesanvo\ndescription: Kirim pesan WhatsApp notifikasi dan dokumen via Pesanvo Gateway API.\n---\n\n# Pesanvo Gateway Skill\n\n{$prompt}\n",
            'openclaw' => json_encode([
                'name' => 'send_whatsapp',
                'description' => 'Mengirim pesan WhatsApp menggunakan Pesanvo Gateway REST API',
                'endpoint' => 'https://wa.pesanvo.id/api/v1/messages/text',
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Api-Key' => '${env.PESANVO_KEY}',
                ],
                'parameters' => [
                    'to' => 'string (nomor telepon WhatsApp penerima, format 08 atau 62)',
                    'message' => 'string (isi pesan teks)',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'antigravity' => json_encode([
                'name' => 'pesanvo',
                'description' => 'WhatsApp Gateway integration rules for Antigravity',
                'api' => [
                    'base_url' => 'https://wa.pesanvo.id/api/v1',
                    'auth_header' => 'X-Api-Key',
                    'env_key' => 'PESANVO_KEY',
                ],
                'instructions' => $prompt,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'opencode' => "# OpenCode Project Instructions\n\n{$prompt}\n",
            'codex' => "# OpenAI Codex Prompt\n\n{$prompt}\n",
            'windsurf' => "# Windsurf Cascade Rules\n\n{$prompt}\n",
            'cline' => "{$prompt}\n",
            'copilot' => "{$prompt}\n",
            default => $prompt,
        };

        $contentType = str_ends_with($filename, '.json')
            ? 'application/json; charset=UTF-8'
            : 'text/markdown; charset=UTF-8';

        $safeFilename = match ($agent) {
            'codex' => 'codex-system-prompt.md',
            default => $filename,
        };

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$safeFilename}\"",
        ]);
    }

    public function agents(): array
    {
        return [
            'claude' => [
                'id' => 'claude',
                'name' => 'Claude Code',
                'tool' => 'Anthropic CLI',
                'file' => 'CLAUDE.md',
                'color' => '#D97757',
                'category' => 'CLI Coding Agent',
                'badge' => 'Official CLI',
                'desc' => 'Agent CLI otonom dari Anthropic yang mengeksekusi perintah terminal, merawat repositori, dan membaca instruksi dari berkas CLAUDE.md.',
                'prompt' => "Tambahkan integrasi WhatsApp Gateway ke project ini menggunakan REST API Pesanvo (https://wa.pesanvo.id).\n\nSpesifikasi integrasi:\n- Base URL: https://wa.pesanvo.id/api/v1\n- Endpoint kirim teks: POST /messages/text\n- Header:\n    X-Api-Key: env('PESANVO_KEY')\n    Content-Type: application/json\n- Payload JSON:\n    {\n      \"to\": \"081234567890\",\n      \"message\": \"Halo! Pesan transaksional dari sistem.\"\n    }\n- Format respon sukses:\n    { \"success\": true, \"data\": { \"id\": \"msg_1029\", \"status\": \"queued\" } }\n\nTolong buatkan helper service yang modular dengan validasi nomor tujuan dan error handling yang aman. Simpan petunjuk ini di berkas CLAUDE.md.",
            ],
            'cursor' => [
                'id' => 'cursor',
                'name' => 'Cursor',
                'tool' => 'AI Code Editor',
                'file' => '.cursorrules',
                'color' => '#000000',
                'category' => 'IDE Agent',
                'badge' => 'Rules Ready',
                'desc' => 'Editor AI mutakhir dengan fitur Composer. Aturan .cursorrules memastikan AI selalu menerapkan best practice integrasi WhatsApp.',
                'prompt' => "# Aturan Pesanvo Gateway untuk .cursorrules\n\nKetika membuat fitur pengiriman pesan, notifikasi, atau verifikasi OTP WhatsApp:\n1. Panggil endpoint REST API: POST https://wa.pesanvo.id/api/v1/messages/text\n2. Autentikasi: sertakan header 'X-Api-Key' dari variabel lingkungan PESANVO_KEY.\n3. Payload JSON:\n   {\n     \"to\": \"08xxxxxxxxxx\",\n     \"message\": \"Isi pesan WhatsApp\"\n   }\n4. Jangan pernah mengekspos API key di client-side / frontend.\n5. Tangani respon galat 401 (kunci salah) dan 422 (data tidak valid) dengan elegan.",
            ],
            'hermes' => [
                'id' => 'hermes',
                'name' => 'Hermes Agent',
                'tool' => 'Nous Research Agent',
                'file' => 'SKILL.md',
                'color' => '#EC4899',
                'category' => 'Persistent Autonomous Agent',
                'badge' => 'Skills Spec',
                'desc' => 'Framework autonomous AI agent dari Nous Research dengan memori persisten lintas sesi dan ekstensibilitas berbasis open standard SKILL.md.',
                'prompt' => "Definisikan skill baru untuk Hermes Agent di folder `skills/pesanvo/SKILL.md`:\n\n```yaml\nname: pesanvo\ndescription: Kirim pesan WhatsApp notifikasi dan dokumen via Pesanvo Gateway API.\n```\n\nInstruksi teknis untuk agent:\n- Base URL: https://wa.pesanvo.id/api/v1\n- Method: POST /messages/text\n- Headers:\n    Content-Type: application/json\n    X-Api-Key: \${PESANVO_KEY}\n- Parameter fungsi `send_whatsapp(to, message)`:\n    `to`: nomor WhatsApp tujuan (format 08 atau 62)\n    `message`: teks pesan WhatsApp yang dikirim\n- Agent harus mengecek apakah API key tersedia sebelum memanggil HTTP POST.\n- Kembalikan ID pesan ('id') dan status ('queued') saat pengiriman sukses.",
            ],
            'openclaw' => [
                'id' => 'openclaw',
                'name' => 'OpenClaw',
                'tool' => 'Autonomous WhatsApp Employee',
                'file' => 'openclaw.json',
                'color' => '#EF4444',
                'category' => 'Local-first ReAct Agent',
                'badge' => 'Tool Calling',
                'desc' => 'Autonomous personal AI employee & coding agent open-source (Peter Steinberger). Mengotomatiskan pesan WhatsApp langsung melalui ReAct loop & MCP.',
                'prompt' => "Konfigurasikan OpenClaw (Clawdbot) dengan tool pengiriman WhatsApp resmi Pesanvo:\n\n1. Daftarkan tool 'send_whatsapp' di manifest tool OpenClaw:\n   - Description: Mengirim notifikasi atau balasan WhatsApp ke pengguna.\n   - Endpoint: POST https://wa.pesanvo.id/api/v1/messages/text\n   - Header: X-Api-Key: \${env.PESANVO_KEY}\n   - Body: { \"to\": \"<recipient_phone>\", \"message\": \"<message_text>\" }\n2. Standarisasi format nomor: normalisasi awalan 08 menjadi 628 secara otomatis.\n3. Bila agent mendeteksi perintah pengiriman pesan atau alert sistem, jalankan tool ini dan laporkan queue ID yang diterima.",
            ],
            'antigravity' => [
                'id' => 'antigravity',
                'name' => 'Antigravity',
                'tool' => 'Google DeepMind Agentic Studio',
                'file' => 'antigravity.json',
                'color' => '#3B82F6',
                'category' => 'Agentic Studio',
                'badge' => 'Multi-Agent',
                'desc' => 'AI coding assistant tingkat lanjut dengan dukungan subagents, declarative rules, dan eksekusi background task mandiri.',
                'prompt' => "Konfigurasikan integrasi WhatsApp otomatis untuk workspace ini menggunakan Pesanvo Gateway API:\n- Base URL: https://wa.pesanvo.id/api/v1\n- Endpoint: POST /messages/text\n- Header: X-Api-Key: [API_KEY_ANDA], Content-Type: application/json\n- Body: { \"to\": \"08xxxxxxxxxx\", \"message\": \"Pesan otomatis sistem\" }\n- Dokumentasi API lengkap: https://wa.pesanvo.id/docs\n- Pastikan fungsi kirim mengembalikan status antrean pesan tanpa menghentikan thread utama aplikasi.",
            ],
            'opencode' => [
                'id' => 'opencode',
                'name' => 'OpenCode',
                'tool' => 'Open-Source AI',
                'file' => 'instructions.md',
                'color' => '#10B981',
                'category' => 'Terminal Coding Tool',
                'badge' => 'Open-Source',
                'desc' => 'Perangkat AI coding open-source berbasis terminal yang ringan dan cepat, siap membaca instruksi proyek langsung dari repositori.',
                'prompt' => "Integrasikan pengiriman pesan WhatsApp via Pesanvo Gateway.\nURL: https://wa.pesanvo.id/api/v1/messages/text\nMethod: POST\nHeaders:\n  X-Api-Key: os.getenv('WA_KEY')\n  Content-Type: application/json\nBody:\n  {\n    \"to\": \"081234567890\",\n    \"message\": \"Pesan verifikasi sistem\"\n  }\nBuatkan modul client HTTP yang bersih dan siap diuji.",
            ],
            'codex' => [
                'id' => 'codex',
                'name' => 'OpenAI Codex',
                'tool' => 'Coding Agent',
                'file' => 'System Prompt',
                'color' => '#6366F1',
                'category' => 'LLM Engine',
                'badge' => 'OpenAI',
                'desc' => 'Model bahasa penalaran kode dari OpenAI untuk scaffolding otomatis, pembuatan unit test, dan fungsi API helper.',
                'prompt' => "Write a clean and robust service module to send WhatsApp messages using Pesanvo Gateway.\nAPI URL: https://wa.pesanvo.id/api/v1/messages/text\nMethod: POST\nHeaders:\n  X-Api-Key: process.env.WA_API_KEY\n  Content-Type: application/json\nBody:\n  { \"to\": recipient_phone, \"message\": text_message }\nRequirements:\n- Validate phone number input (supports 08... or 628...)\n- Parse JSON response and log queue message ID\n- Add exponential retry on 5xx server errors",
            ],
            'windsurf' => [
                'id' => 'windsurf',
                'name' => 'Windsurf',
                'tool' => 'Cascade Flow',
                'file' => '.windsurfrules',
                'color' => '#09B6A2',
                'category' => 'Agentic IDE',
                'badge' => 'Codeium',
                'desc' => 'IDE Agentic dari Codeium dengan Cascade Flow yang memahami seluruh konteks codebase secara mendalam.',
                'prompt' => "Integrasikan Pesanvo Gateway API ke dalam alur aplikasi:\n- Endpoint: POST https://wa.pesanvo.id/api/v1/messages/text\n- Header: X-Api-Key: env('WA_API_KEY')\n- Request Body: { \"to\": \"081234567890\", \"message\": \"Notifikasi pesanan siap dikirim\" }\n- Tangani status response: 'queued' menandakan pesan telah masuk antrean pengiriman server.",
            ],
            'cline' => [
                'id' => 'cline',
                'name' => 'Cline / Roo Code',
                'tool' => 'Autonomous Agent',
                'file' => '.clinerules',
                'color' => '#F59E0B',
                'category' => 'Autonomous Coding',
                'badge' => 'Rules File',
                'desc' => 'Extension VS Code otonom yang dapat membuat file, menjalankan command terminal, dan mengintegrasikan API dengan panduan .clinerules.',
                'prompt' => "# Panduan Pesanvo Gateway untuk .clinerules\n- Base URL: https://wa.pesanvo.id/api/v1\n- Endpoint Kirim Pesan: POST /messages/text\n- Headers:\n    Content-Type: application/json\n    X-Api-Key: \${WA_GATEWAY_KEY}\n- Buatkan service class modular dengan error handling yang aman tanpa menghentikan flow aplikasi saat network failure.",
            ],
            'copilot' => [
                'id' => 'copilot',
                'name' => 'GitHub Copilot',
                'tool' => 'Copilot Workspace',
                'file' => 'copilot-instructions.md',
                'color' => '#1F2937',
                'category' => 'AI Pair Programmer',
                'badge' => 'GitHub',
                'desc' => 'Asisten AI dari GitHub. File instruksi khusus memastikan Copilot selalu mematuhi arsitektur REST API Pesanvo Gateway.',
                'prompt' => "# GitHub Copilot Instructions for WhatsApp Gateway\nWhen implementing WhatsApp notifications, OTP, or messaging:\n- Use Pesanvo Gateway REST API: POST https://wa.pesanvo.id/api/v1/messages/text\n- Always read API key from environment variable `WA_GATEWAY_KEY` (Header: `X-Api-Key`)\n- Validate phone numbers to support standard Indonesian formats (08... and 62...)\n- Avoid throwing fatal exceptions on notification delivery failure.",
            ],
        ];
    }

    public function sdkPrompts(): array
    {
        return [
            'php' => [
                'title' => 'PHP / Laravel',
                'lang' => 'PHP 8.2+ & Laravel',
                'badge' => 'Service Pattern',
                'desc' => 'Membuat config/whatsapp.php dan App\Services\WhatsAppGateway modular dengan Http client bawaan Laravel.',
                'prompt' => "Buatkan modul client WhatsApp resmi untuk aplikasi Laravel ini:\n1. Buat file `config/whatsapp.php` yang membaca `WA_GATEWAY_URL` (default: https://wa.pesanvo.id), `WA_GATEWAY_KEY`, dan `WA_GATEWAY_SESSION` dari .env.\n2. Buat service class `App\\Services\\WhatsAppGateway` dengan method:\n   - `kirim(?string \$nomor, string \$pesan): bool` -> memanggil POST /api/v1/messages/text\n   - `template(?string \$nomor, string \$template, array \$variabel = []): bool` -> memanggil POST /api/v1/messages/template\n   - `media(?string \$nomor, string \$filePath, string \$caption = '', string \$type = 'image'): bool` -> memanggil POST /api/v1/messages/media\n3. Pastikan method selalu mengembalikan boolean aman tanpa exception crash, sertakan timeout 5 detik, dan tulis log peringatan saat terjadi galat.",
            ],
            'nodejs' => [
                'title' => 'Node.js / TypeScript',
                'lang' => 'TypeScript / ESModule',
                'badge' => 'Type Safe',
                'desc' => 'Membuat modul client pesanvo dengan fetch natif, Promise handler, dan deklarasi tipe TypeScript.',
                'prompt' => "Buatkan client library Pesanvo Gateway untuk project Node.js / TypeScript ini:\n1. Buat berkas `src/services/pesanvoWa.ts` dengan interface `WhatsAppMessagePayload` dan `WhatsAppResponse`.\n2. Buat fungsi helper `kirimWhatsApp(nomor: string, pesan: string): Promise<boolean>` yang memanggil `POST https://wa.pesanvo.id/api/v1/messages/text`.\n3. Gunakan fetch natif dengan header `X-Api-Key: process.env.WA_GATEWAY_KEY` dan `Content-Type: application/json`.\n4. Sertakan timeout signal 5000ms dan penanganan galat yang aman.",
            ],
            'python' => [
                'title' => 'Python',
                'lang' => 'Python 3.10+ (requests/httpx)',
                'badge' => 'Clean OOP',
                'desc' => 'Membuat class PesanvoGateway dengan normalisasi nomor otomatis dan logging berbasis standar library.',
                'prompt' => "Buatkan client library Python untuk Pesanvo Gateway:\n1. Buat class `PesanvoGateway` di berkas `services/whatsapp_gateway.py`.\n2. Ambil URL (https://wa.pesanvo.id) dan KEY dari `os.environ.get('WA_GATEWAY_KEY')`.\n3. Sediakan method `kirim(nomor: str, pesan: str) -> bool` yang memanggil endpoint `POST /api/v1/messages/text`.\n4. Tambahkan fungsi normalisasi nomor agar awalan '08' otomatis dikonversi ke format yang valid.",
            ],
            'curl' => [
                'title' => 'Bash / cURL',
                'lang' => 'Shell Script',
                'badge' => 'Zero Dependency',
                'desc' => 'Script CLI bash portabel untuk automasi server, CI/CD pipeline, atau cron job pengiriman notifikasi.',
                'prompt' => "Buatkan script bash `kirim-wa.sh` yang menerima 2 argumen: \$1 (nomor tujuan) dan \$2 (isi pesan):\n- Periksa keberadaan variabel lingkungan \$WA_KEY sebelum mengirim.\n- Jalankan curl -s -X POST https://wa.pesanvo.id/api/v1/messages/text \\\n    -H \"X-Api-Key: \$WA_KEY\" \\\n    -H \"Content-Type: application/json\" \\\n    -d \"{\\\"to\\\": \\\"\$1\\\", \\\"message\\\": \\\"\$2\\\"}\"\n- Tampilkan pesan sukses jika status HTTP 200/202.",
            ],
        ];
    }

    public function firstPrompts(): array
    {
        return [
            'otp' => [
                'title' => 'Kirim OTP & Kode Verifikasi',
                'tag' => 'Autentikasi & Keamanan',
                'icon' => 'bi-shield-check',
                'prompt' => "Tolong buatkan alur verifikasi nomor WhatsApp untuk pengguna baru:\n1. Generate kode OTP acak 6 angka dan simpan di cache selama 5 menit.\n2. Kirim kode OTP tersebut ke WhatsApp pengguna menggunakan Pesanvo Gateway via POST https://wa.pesanvo.id/api/v1/messages/text dengan pesan: 'Kode verifikasi Anda adalah [KODE]. Jangan berikan kode ini kepada siapapun demi keamanan akun Anda.'\n3. Buatkan endpoint verifikasi untuk mencocokkan input kode pengguna.",
            ],
            'invoice' => [
                'title' => 'Kirim Tagihan & Invoice PDF',
                'tag' => 'E-Commerce & Billing',
                'icon' => 'bi-receipt',
                'prompt' => "Tolong buatkan fungsi otomatis untuk mengirimkan berkas tagihan/invoice PDF pesanan ke WhatsApp pelanggan:\n1. Ambil file PDF invoice yang tersimpan di storage.\n2. Panggil endpoint media Pesanvo: POST https://wa.pesanvo.id/api/v1/messages/media dengan multipart form-data (to, caption, type='document', file).\n3. Sertakan caption: 'Halo [Nama], pesanan #[NomorOrder] telah terbit. Silakan unduh bukti transaksi terlampir. Terima kasih!'",
            ],
            'webhook' => [
                'title' => 'Webhook Bot Otomatis (Auto-Responder)',
                'tag' => 'Chatbot & Layanan Pelanggan',
                'icon' => 'bi-robot',
                'prompt' => "Buatkan endpoint webhook penerima pesan masuk dari Pesanvo Gateway di route `POST /api/webhook/whatsapp`:\n1. Validasi signature pengirim dari header `X-Pesanvo-Signature` untuk keamanan.\n2. Ekstrak pesan dari payload (pengirim `from` dan teks `message`).\n3. Jika pengguna mengetik kata 'BANTUAN' atau 'MENU', balas otomatis dengan daftar layanan kami menggunakan panggilan balik POST https://wa.pesanvo.id/api/v1/messages/text.",
            ],
            'broadcast' => [
                'title' => 'Broadcast Notifikasi Transaksional',
                'tag' => 'Batch Processing',
                'icon' => 'bi-megaphone',
                'prompt' => "Buatkan command background job untuk mengirimkan pengumuman transaksional ke sekumpulan nomor pelanggan:\n1. Ambil daftar pelanggan yang berstatus aktif.\n2. Gunakan endpoint bulk POST https://wa.pesanvo.id/api/v1/messages/bulk dengan array data nomor dan pesan yang dipersonalisasi.\n3. Gateway akan mengatur jeda pengiriman otomatis anti-blokir di server, tugas kita hanya memantau status 'queued' yang dikembalikan.",
            ],
        ];
    }

    public function cliSnippets(): array
    {
        return [
            'curl_text' => [
                'title' => 'cURL Kirim Pesan Teks',
                'command' => 'curl -X POST https://wa.pesanvo.id/api/v1/messages/text \\'."\n".
                             '  -H "X-Api-Key: vwa_live_xxxxxxxxxxxxxxxx" \\'."\n".
                             '  -H "Content-Type: application/json" \\'."\n".
                             '  -d \'{"to":"081234567890","message":"Halo dari terminal CLI Pesanvo!"}\'',
            ],
            'curl_status' => [
                'title' => 'cURL Cek Status Gateway',
                'command' => 'curl -s https://wa.pesanvo.id/status.json',
            ],
            'powershell' => [
                'title' => 'PowerShell (Windows)',
                'command' => '$headers = @{ "X-Api-Key" = "vwa_live_xxxxxxxxxxxxxxxx"; "Content-Type" = "application/json" }'."\n".
                             '$body = @{ to = "081234567890"; message = "Halo dari PowerShell!" } | ConvertTo-Json'."\n".
                             'Invoke-RestMethod -Uri "https://wa.pesanvo.id/api/v1/messages/text" -Method Post -Headers $headers -Body $body',
            ],
            'artisan' => [
                'title' => 'Artisan CLI Runner (Laravel)',
                'command' => 'php -r \'echo file_get_contents("https://wa.pesanvo.id/status.json");\'',
            ],
        ];
    }
}
