# Integrasi AI Agent

Panduan prompt dan integrasi untuk Claude Code, Cursor, Antigravity, OpenCode, Codex, dan asisten AI lainnya.

---

## Mengapa Pesanvo Gateway Ramah AI Agent?

AI coding assistant bekerja paling baik dengan arsitektur yang sederhana, deterministik, dan terdokumentasi rapi:

1. **REST API Standar:** Tanpa pustaka pihak ketiga yang rumit, cukup panggil HTTP POST dengan JSON standar.
2. **Autentikasi Bersih:** Autentikasi cukup lewat header `X-Api-Key`.
3. **Payload Konsisten:** Permintaan kirim teks hanya membutuhkan dua field utama: `to` dan `message`.
4. **Antrean Otomatis:** AI agent Anda tidak perlu merancang algoritma retry atau jeda anti-spam dari nol karena gateway menanganinya secara mandiri di sisi server.

---

## 1. Prompt Instan untuk AI Coding Agent

Salin salah satu prompt di bawah ini langsung ke obrolan AI agent Anda (Claude Code, Cursor Composer, Antigravity, OpenCode, atau Codex):

### Prompt Umum (Universal)

```text
Integrasikan pengiriman pesan WhatsApp ke dalam project ini menggunakan Pesanvo Gateway.

Spesifikasi teknis:
- Base URL: https://wa.pesanvo.id/api/v1
- Endpoint kirim pesan teks: POST /messages/text
- Header autentikasi: 
    Content-Type: application/json
    X-Api-Key: [API_KEY_ANDA]
- Format request body:
    {
      "to": "08xxxxxxxxxx",
      "message": "Isi pesan WhatsApp"
    }
- Format nomor: Format lokal (08...) atau internasional (628...), gateway otomatis menormalisasi nomor.
- Response sukses:
    {
      "success": true,
      "data": { "id": "msg_xxx", "status": "queued" }
    }

Tolong buatkan modul helper/service yang rapi, simpan API key di variabel lingkungan (.env), dan sertakan penanganan galat jika permintaan gagal.
```

---

## 2. Aturan Cursor (.cursorrules)

Jika Anda menggunakan **Cursor**, buat berkas `.cursorrules` di root repositori Anda dan tempelkan instruksi berikut:

```markdown
# Aturan WhatsApp Gateway (Pesanvo)

Ketika menambahkan fitur pengiriman pesan, OTP, verifikasi, atau notifikasi WhatsApp:
- Selalu gunakan endpoint REST API: `POST https://wa.pesanvo.id/api/v1/messages/text`
- Jangan pernah melakukan hardcode API key ke dalam berkas kode. Gunakan variabel lingkungan `WA_GATEWAY_KEY`.
- Payload JSON:
  - `to` (string, wajib): nomor penerima (contoh: "081234567890")
  - `message` (string, wajib): isi pesan teks WhatsApp
- Kirim pesan selalu dari backend server, jangan pernah mengekspos API key di kode client-side (frontend).
- Tangani respon galat HTTP 401 (kunci tidak valid) dan HTTP 422 (format data salah) secara elegan.
```

---

## 3. Instruksi Claude Code (CLAUDE.md)

Untuk pengguna **Claude Code CLI**, tambahkan bagian berikut ke dalam berkas `CLAUDE.md` di direktori proyek:

```markdown
## Integrasi WhatsApp Gateway

Project ini menggunakan Pesanvo Gateway untuk pesan transaksional:
- Dokumentasi: https://wa.pesanvo.id/docs
- API Endpoint: `POST https://wa.pesanvo.id/api/v1/messages/text`
- Headers: `X-Api-Key: <env:WA_KEY>`, `Content-Type: application/json`
- Request: `{ "to": "<phone>", "message": "<text>" }`
- Buat reusable service class atau helper function saat menambahkan pesan baru.
```

---

## 4. Konfigurasi Antigravity / Agentic Sidecar

Bagi Anda yang memakai asisten cerdas berbasis **Antigravity**, definisikan petunjuk berikut:

```text
Buatkan service pengiriman notifikasi WhatsApp berbasis Pesanvo Gateway:
- Endpoint: POST https://wa.pesanvo.id/api/v1/messages/text
- Header: X-Api-Key diambil dari konfigurasi aman.
- Pastikan method mengembalikan status boolean berhasil/gagal tanpa menghentikan flow utama aplikasi jika terjadi kegagalan jaringan.
```

---

## 5. Konfigurasi OpenCode & Codex

Pada IDE atau agen otonom berbasis OpenCode dan OpenAI Codex:

```python
# Contoh petunjuk untuk Codex / OpenCode:
# Tulis fungsi kirim_whatsapp(nomor, pesan) yang memanggil:
# POST https://wa.pesanvo.id/api/v1/messages/text
# Headers: {'X-Api-Key': os.environ['WA_GATEWAY_KEY']}
# Body: {'to': nomor, 'message': pesan}
# Sertakan try-except dan logging yang informatif.
```

---

## 6. Konfigurasi Hermes Agent (SKILL.md)

Bagi pengguna **Hermes Agent** (Nous Research) yang memakai standar skill berbasis `SKILL.md`:

```yaml
# Simpan di skills/pesanvo/SKILL.md
name: pesanvo
description: Kirim notifikasi WhatsApp dan dokumen via Pesanvo Gateway API.
```

Prompt instruksi untuk Hermes Agent:
```text
Definisikan tool send_whatsapp(to, message):
- Base URL: https://wa.pesanvo.id/api/v1
- Endpoint: POST /messages/text
- Headers:
    Content-Type: application/json
    X-Api-Key: ${PESANVO_KEY}
- Normalisasi nomor penerima: dukung awalan 08 maupun 62.
- Laporkan status antrean (queued) dan ID pesan kembali ke user.
```

---

## 7. Konfigurasi OpenClaw (Autonomous AI Employee)

Untuk pengguna **OpenClaw** (personal autonomous agent):

```json
{
  "name": "send_whatsapp",
  "description": "Mengirim pesan WhatsApp menggunakan Pesanvo Gateway",
  "endpoint": "https://wa.pesanvo.id/api/v1/messages/text",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json",
    "X-Api-Key": "${env.PESANVO_KEY}"
  },
  "parameters": {
    "to": "string (nomor telepon WhatsApp penerima)",
    "message": "string (isi pesan teks)"
  }
}
```

Prompt untuk OpenClaw:
```text
Hubungkan tool send_whatsapp ke alur kerja ReAct Anda. Setiap kali ada notifikasi penting atau alert server, panggil tool ini untuk meneruskan pesan ke WhatsApp admin.
```

---

## 8. Praktik Keamanan Kunci API

Saat bekerja dengan AI Agent:
1. **Simpan di Variabel Lingkungan:** Jangan pernah memasukkan nilai asli API key ke dalam prompt AI publik atau berkas kode yang diunggah ke repositori.
2. **Gunakan Nilai Dummy saat Generasi Kode:** Berikan contoh seperti `vwa_live_contoh123` saat AI menuliskan kode, lalu isi nilai sebenarnya di berkas `.env` lokal Anda.
3. **Cabut Kunci jika Terbocorkan:** Jika API key tidak sengaja tertulis di prompt atau log AI, segera cabut (*revoke*) kunci tersebut di halaman Dashboard dan buat kunci baru.
