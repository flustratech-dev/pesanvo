<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\MessageTemplate;
use App\Support\TemplateBawaan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.templates.index', [
            'templates' => $workspace->templates()->orderBy('name')->get(),
            'templateBawaan' => TemplateBawaan::perKategori(),
            'slugTerpakai' => $workspace->templates()->pluck('slug')->all(),
        ]);
    }

    /**
     * Menyalin template bawaan menjadi milik workspace.
     *
     * Menyalin, bukan menautkan: sejak disalin ia milik pelanggan sepenuhnya
     * dan tidak pernah ikut berubah kalau kami memperbaiki kalimat bawaannya.
     * Itu memang yang diinginkan — orang yang sudah menyesuaikan template
     * dengan gaya bahasanya sendiri tidak boleh tiba-tiba kehilangannya karena
     * kami mengganti satu kata.
     */
    public function copyBuiltin(Request $request, string $slug): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);
        $bawaan = TemplateBawaan::cari($slug);

        if (! $bawaan) {
            return back()->withErrors(['template' => 'Template bawaan itu sudah tidak ada.']);
        }

        // Slug unik per workspace; yang sudah punya salinannya diberi akhiran
        // supaya menyalin dua kali tidak gagal dengan galat database.
        $slugBaru = $bawaan['slug'];
        $n = 2;
        while ($workspace->templates()->where('slug', $slugBaru)->exists()) {
            $slugBaru = $bawaan['slug'].'-'.$n++;
        }

        $workspace->templates()->create([
            'name' => $bawaan['name'],
            'slug' => $slugBaru,
            'body' => $bawaan['body'],
            'variables' => $bawaan['variables'],
            'is_active' => true,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Template disalin',
            'pesan' => '"'.$bawaan['name'].'" sekarang milik workspace ini dan bisa Anda ubah sesuka hati.',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('message_templates')->where('workspace_id', $workspace->id)],
            'body' => ['required', 'string', 'max:4096'],
        ]);

        $template = $workspace->templates()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'body' => $data['body'],
            // Selalu diturunkan dari isi body, bukan diisi manual, supaya
            // daftar variabel tidak pernah bisa melenceng dari templatenya.
            'variables' => MessageTemplate::extractVariables($data['body']),
        ]);

        return back()->with('status', "Template '{$template->name}' disimpan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = EnsureWorkspaceSelected::from($request)->templates()->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:4096'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template->update([
            'name' => $data['name'],
            'body' => $data['body'],
            'variables' => MessageTemplate::extractVariables($data['body']),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('status', 'Template diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        EnsureWorkspaceSelected::from($request)->templates()->findOrFail($id)->delete();

        return back()->with('status', 'Template dihapus.');
    }
}
