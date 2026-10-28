<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiIntegrationPageTest extends TestCase
{
    public function test_halaman_ai_bisa_diakses_publik(): void
    {
        $response = $this->get('/ai');

        $response->assertOk()
            ->assertSee('Integrasi WhatsApp dengan')
            ->assertSee('AI Agent')
            ->assertSee('Salin Prompt Instalasi')
            ->assertSee(route('ai.markdown'))
            ->assertSee('Unduh .md');
    }

    public function test_redirect_integrasi_ai_ke_ai(): void
    {
        $this->get('/integrasi-ai')
            ->assertRedirect('/ai');
    }

    public function test_halaman_ai_memuat_semua_agent(): void
    {
        $response = $this->get('/ai');

        $response->assertOk()
            ->assertSee('Claude Code')
            ->assertSee('Cursor')
            ->assertSee('Hermes Agent')
            ->assertSee('OpenClaw')
            ->assertSee('Antigravity')
            ->assertSee('OpenCode')
            ->assertSee('OpenAI Codex')
            ->assertSee('Windsurf')
            ->assertSee(route('ai.agent.download', 'claude'))
            ->assertSee(route('ai.agent.download', 'cursor'))
            ->assertSee(route('ai.agent.download', 'hermes'));
    }

    public function test_halaman_ai_memuat_empat_bagian_integrasi(): void
    {
        $response = $this->get('/ai');

        $response->assertOk()
            // 1. Jalankan install prompt SDK Resmi
            ->assertSee('Jalankan Install Prompt SDK Resmi')
            ->assertSee('PHP / Laravel')
            ->assertSee('Node.js / TypeScript')
            ->assertSee('Python')
            // 2. Hubungkan AI ke wa gateway pesanvo
            ->assertSee('Hubungkan AI ke WA Gateway Pesanvo')
            ->assertSee('WA_GATEWAY_URL')
            ->assertSee('WA_GATEWAY_KEY')
            // 3. Tulis prompt pertama kamu
            ->assertSee('Tulis Prompt Pertama Kamu')
            ->assertSee('Kirim OTP & Kode Verifikasi')
            ->assertSee('Kirim Tagihan & Invoice PDF')
            // 4. CLI
            ->assertSee('CLI (Command Line Interface')
            ->assertSee('curl -X POST https://wa.pesanvo.id/api/v1/messages/text');
    }

    public function test_landing_page_memuat_tombol_lihat_lengkap_dan_agent_baru(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee(route('ai.index'))
            ->assertSee('Lihat Lengkap Panduan AI')
            ->assertSee('Hermes')
            ->assertSee('OpenClaw');
    }

    public function test_docs_sidebar_memuat_tombol_ke_halaman_ai(): void
    {
        $response = $this->get('/docs/integrasi-ai-agent');

        $response->assertOk()
            ->assertSee(route('ai.index'))
            ->assertSee('Integrasi AI Agent')
            ->assertSee('Hermes Agent')
            ->assertSee('OpenClaw');
    }

    public function test_unduh_panduan_master_markdown(): void
    {
        $response = $this->get('/ai/panduan.md');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('Pesanvo Gateway - Panduan Integrasi AI Agent & Developer', false)
            ->assertSee('POST /api/v1/messages/text')
            ->assertSee('Claude Code')
            ->assertSee('Hermes Agent');
    }

    public function test_unduh_berkas_spesifik_agent(): void
    {
        $this->get('/ai/agent/claude/unduh')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('Claude Code');

        $this->get('/ai/agent/hermes/unduh')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('pesanvo');

        $this->get('/ai/agent/openclaw/unduh')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertSee('send_whatsapp');
    }

    public function test_unduh_raw_markdown_dokumentasi(): void
    {
        $response = $this->get('/docs/integrasi-ai-agent.md');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('Integrasi AI Agent');
    }
}
