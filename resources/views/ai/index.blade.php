<!DOCTYPE html>
<html lang="id" class="scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.seo-head', [
        'title' => 'Integrasi AI Agent WhatsApp Gateway · ' . config('app.name'),
        'description' => 'Panduan integrasi lengkap Pesanvo Gateway untuk AI Coding Agent: Claude Code, Cursor, Hermes Agent, OpenClaw, Antigravity, OpenCode, Codex, dan Windsurf. Salin prompt instalasi sekali klik.',
    ])

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/favicon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('animasi-muncul');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased selection:bg-primary selection:text-primary-foreground overflow-x-clip w-full relative"
      x-data="{
          mobileMenu: false,
          disalinKey: null,
          previewAgent: null,
          tabSdk: 'php',
          langkahAktif: 1,
          salinTeks(teks, key) {
              navigator.clipboard.writeText(teks);
              this.disalinKey = key;
              setTimeout(() => { if (this.disalinKey === key) this.disalinKey = null; }, 2200);
          }
      }">

@include('partials.header')

{{-- ===================== HERO SECTION ===================== --}}
<section class="relative overflow-hidden pt-12 pb-16 sm:pt-16 sm:pb-24 border-b border-border/60">
    <div class="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.primary.DEFAULT/8%),transparent)]"></div>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <h1 class="muncul text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-foreground leading-[1.15]">
                Integrasi WhatsApp dengan <span class="text-primary font-bold">AI Agent & Coding Assistant</span>
            </h1>

            <p class="muncul mt-5 text-sm sm:text-base lg:text-lg leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                Hubungkan asisten AI favorit Anda (Claude Code, Cursor, Hermes Agent, OpenClaw, Antigravity, dan lainnya) ke Pesanvo Gateway. Salin prompt instalasi satu-klik, pasang aturan rules, dan biarkan AI menyusun integrasi pesan WhatsApp otomatis tanpa repot.
            </p>

            <div class="muncul mt-8 flex flex-wrap items-center gap-3" style="--tunda: 110ms">
                <a href="#katalog-agent" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-[0.98]">
                    <i class="bi bi-robot"></i>
                    <span>Pilih AI Agent Anda</span>
                </a>
                <a href="#langkah-integrasi" class="inline-flex items-center gap-2 rounded-xl border border-input bg-card px-5 py-3 text-sm font-medium text-foreground hover:bg-muted transition-all">
                    <i class="bi bi-diagram-3"></i>
                    <span>4 Langkah Integrasi</span>
                </a>
                <a href="{{ route('ai.markdown') }}" download class="inline-flex items-center gap-2 rounded-xl border border-input bg-card px-4 py-3 text-sm font-medium text-foreground hover:bg-muted hover:border-primary/40 transition-all shadow-2xs" title="Unduh spesifikasi lengkap format Markdown (.md)">
                    <i class="bi bi-filetype-md text-primary font-bold"></i>
                    <span>Unduh .md</span>
                </a>
                <a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="inline-flex items-center gap-1.5 px-3 py-3 text-sm font-medium text-muted-foreground hover:text-primary transition-colors">
                    <span>Baca di Docs</span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>

            {{-- 4 Pilar Keunggulan AI Gateway --}}
            <div class="muncul mt-10 grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-border/60" style="--tunda: 160ms">
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <i class="bi bi-check2-circle text-primary"></i> REST API Murni
                    </span>
                    <p class="text-[11px] text-muted-foreground">JSON standar tanpa wrapper kompleks</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <i class="bi bi-shield-lock text-primary"></i> Header X-Api-Key
                    </span>
                    <p class="text-[11px] text-muted-foreground">Autentikasi bersih ramah AI</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <i class="bi bi-layers text-primary"></i> Antrean Otomatis
                    </span>
                    <p class="text-[11px] text-muted-foreground">Server mengelola jeda anti-blokir</p>
                </div>
                <div class="space-y-1">
                    <span class="text-xs font-semibold text-foreground flex items-center gap-1.5">
                        <i class="bi bi-terminal text-primary"></i> 10+ AI Agents
                    </span>
                    <p class="text-[11px] text-muted-foreground">Prompt spesifik per framework</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== KATALOG AI AGENT (SALIN PROMPT INSTALASI) ===================== --}}
<section id="katalog-agent" class="py-16 sm:py-20 bg-muted/20">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-primary">Pilihan Agent & IDE</span>
                <h2 class="muncul mt-1.5 text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                    Salin Prompt Instalasi AI Agent
                </h2>
                <p class="muncul mt-2 text-xs sm:text-sm text-muted-foreground max-w-2xl">
                    Setiap kartu menyajikan prompt spesifik yang sudah dioptimalkan untuk karakter masing-masing agent. Klik <strong>Salin Prompt</strong> lalu tempelkan langsung ke chat AI atau simpan di berkas konfigurasinya.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-2 text-xs text-muted-foreground">
                <i class="bi bi-info-circle text-primary"></i>
                <span>Format prompt ringkas & langsung siap pakai</span>
            </div>
        </div>

        {{-- Grid Kartu AI Agent --}}
        <div class="muncul mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" style="--tunda: 60ms">
            @foreach ($agents as $key => $agent)
                <div class="group relative flex flex-col justify-between rounded-2xl border border-border/80 bg-card p-5 shadow-xs transition-all hover:border-primary/50 hover:shadow-md">
                    <div>
                        {{-- Header Kartu: Icon, Nama, Badge --}}
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                @if ($key === 'claude')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#D97757]/15 border border-[#D97757]/30 text-[#D97757] shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="#D97757">
                                            <path d="m4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z"/>
                                        </svg>
                                    </div>
                                @elseif ($key === 'cursor')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 border border-zinc-800 dark:border-zinc-200 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23"/>
                                        </svg>
                                    </div>
                                @elseif ($key === 'hermes')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-pink-500/15 border border-pink-500/30 text-pink-500 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
                                            <path d="M5.938 12.835c.127-.039.285.02.373.143.028.038.036.092.046.14.003.014-.02.033-.04.05-.124-.098-.24-.194-.354-.291-.011-.01-.016-.027-.025-.042zM8.396 9.412c.195-.032.39-.06.588-.05a.54.54 0 01.148.026c.202.071.402.147.601.224.028.01.05.036.075.055l-.013.027a9.203 9.203 0 01-.26-.089c-.115-.038-.213-.077-.315-.098-.25-.05-.25-.046-.292-.014l.574.144c.275.139.55.276.823.417.042.022.09.057.107.098.026.06.063.076.117.072.066-.006.132-.017.213-.027l-.04.086c.051.08.142.02.216.064-.074.13-.247.09-.334.199l.061.074-.12.087c0 .106-.038.168-.306.243l.026.085-.196.042.07.124h-.25l-.007.137c-.081-.01-.161-.018-.244-.027l-.053.123c-.027-.008-.052-.011-.073-.023-.067-.038-.128-.056-.195.006-.019.017-.063.014-.093.008-.026-.006-.05-.029-.07-.042-.11.095-.11.095-.208.003-.057.046-.12.074-.186.011-.063.027-.123-.02-.178-.014-.07.007-.097-.035-.133-.07l-.13.033c-.013-.236-.194-.19-.34-.203.005-.072.05-.092.095-.094a.474.474 0 01.159.022c.164.05.32.12.496.138.203.021.405.029.601-.015.265-.059.52-.149.707-.365.049-.056.083-.127.117-.195.019-.038.02-.084-.02-.116a1.397 1.397 0 00-.382-.217c.024.12-.031.182-.115.221 0 .014-.004.025 0 .03.08.115.084.16-.007.267a1.39 1.39 0 01-.218.211.477.477 0 01-.641-.05 1.36 1.36 0 01-.133-.152c-.078-.107-.076-.108-.033-.236-.165-.08-.128-.226-.104-.364.008-.05.028-.096.049-.163-.04.014-.067.017-.087.032a.897.897 0 00-.316.357c-.007.016-.01.034-.02.047-.012.015-.034.038-.045.035-.02-.006-.037-.027-.05-.045-.008-.012-.007-.032-.012-.057h-.126l.053-.172a14.82 14.82 0 00-.039-.049l.11-.284c-.06.026-.091.044-.124.051-.03.007-.064 0-.095 0 0-.031-.01-.07.004-.092.149-.22.305-.428.593-.476z"/>
                                            <path d="M8.06 10.788c-.003-.038-.004-.075.037-.062.016.006.034.048.028.067-.01.04-.038.032-.064-.005z"/>
                                            <path clip-rule="evenodd" d="M11.981.009c.226-.012.453-.011.679 0 .247.01.495.024.74.062.401.064.798.157 1.19.273.463.138.92.299 1.356.511a7.31 7.31 0 012.948 2.642c.292.469.536.963.739 1.479.219.556.446 1.11.623 1.683.204.654.329 1.326.458 1.997.097.504.182 1.01.29 1.511.156.722.329 1.44.494 2.16.186.812.4 1.615.63 2.415.102.355.193.713.282 1.072.11.436.202.876.254 1.323.031.278.066.557.073.837a7.56 7.56 0 01-.017.88c-.037.413-.1.818-.226 1.212a5.017 5.017 0 01-.915 1.649l-.13.156.018.023c.043-.023.088-.041.127-.068.2-.138.373-.307.531-.49.4-.46.721-.973.975-1.529a3.59 3.59 0 00.325-1.72c-.024-.424-.097-.834-.3-1.213-.013-.027-.015-.06-.03-.121.05.035.082.048.101.072.107.13.22.258.315.398.33.494.46 1.052.486 1.64a3.75 3.75 0 01-.47 1.97c-.36.655-.887 1.14-1.526 1.506-.193.111-.394.21-.595.308-.157.078-.248.211-.318.365a.522.522 0 00-.033.406.359.359 0 01.013.139c-.005.077-.077.155-.14.162-.054.006-.125-.043-.15-.116a1.206 1.206 0 01-.06-.233c-.04-.314-.155-.6-.308-.87a3.906 3.906 0 00-.73-.91 2.129 2.129 0 00-.897-.524 4.093 4.093 0 00-.692-.131c-.075-.008-.15-.04-.22.01.18.06.363.11.538.18.434.173.82.43 1.18.728.308.255.58.543.794.884.098.155.186.315.227.496.027.123.042.25.067.375.013.062-.002.109-.053.144-.047.033-.122.034-.163-.01a.455.455 0 01-.08-.14c-.03-.073-.038-.159-.078-.225a7.314 7.314 0 00-1.423-1.664c-.16-.137-.329-.26-.537-.323-.376-.114-.753-.203-1.15-.154-.213.025-.427.032-.64.053a1.6 1.6 0 00-.736.278 5.14 5.14 0 00-.834.72c-.329.342-.642.699-.955 1.055-.136.155-.264.319-.314.531a5.227 5.227 0 00-.012.051.096.096 0 01-.09.076h-.31c-.046 0-.082-.048-.072-.094.023-.108.045-.216.07-.324.075-.325.19-.635.368-.917.024-.039.04-.088.104-.08l.01.049.027.077c.28-.435.571-.834.996-1.135.283-.204.584-.378.89-.55a.196.196 0 00-.098-.002c-.162.043-.325.084-.485.134-.402.124-.764.33-1.11.566-.147.1-.298.193-.414.333a7.314 7.314 0 00-1.07 1.767.845.845 0 00-.04.12.075.075 0 01-.072.056h-.494c-.04 0-.062-.051-.036-.082.123-.14.246-.282.377-.415.275-.281.58-.532.777-.884.027-.048.063-.09.095-.135.238-.333.54-.607.818-.902.082-.086.175-.16.26-.24.029-.027.053-.057.079-.085l-.018-.025-.135.041c-.034.017-.07.031-.102.05-.248.144-.494.292-.743.433-.408.23-.825.439-1.209.711-.281.2-.591.358-.889.533-.02.012-.044.015-.08.028-.015-.135.143-.201.108-.336-.033.014-.064.02-.085.038-.111.096-.227.19-.328.296-.148.157-.284.325-.425.488-.125.143-.25.286-.373.431A.153.153 0 019.89 24H8.762a.316.316 0 00.016-.042c.028-.09.085-.172.083-.28-.091-.018-.162.001-.212.077a4.45 4.45 0 00-.136.215c-.01.016-.024.03-.042.03h-.093c-.019 0-.029-.022-.017-.037.071-.088.14-.178.209-.268.001-.002-.006-.012-.012-.024-.014.004-.03.006-.045.013-.176.09-.352.181-.527.274a.363.363 0 01-.168.042H5.202c-.026 0-.039-.036-.019-.053.21-.178.402-.374.558-.605.335-.496.538-1.047.667-1.629.004-.02-.003-.043-.006-.091-.037.048-.059.072-.076.1a1.943 1.943 0 01-.334.415c-.28.258-.59.448-.983.464-.297.012-.588 0-.865-.127-.46-.21-.722-.57-.794-1.072-.025-.17-.017-.171-.182-.219A3.513 3.513 0 011.97 20.6a2.286 2.286 0 01-.808-1.13 3.569 3.569 0 01-.16-1.245c.002-.034.016-.067.024-.1.032.023.046.043.05.066.033.153.059.308.096.46.086.355.257.664.516.92.258.256.571.419.91.532.358.118.717.138 1.07-.016a1.89 1.89 0 00.621-.452c.328-.348.533-.76.648-1.223.009-.034.005-.071.007-.11-.015.006-.026.006-.03.011-.031.05-.064.1-.093.152-.284.502-.679.887-1.196 1.135-.351.17-.718.255-1.11.159a1.607 1.607 0 01-.971-.64 2.006 2.006 0 01-.368-.924 2.903 2.903 0 01.02-.886c.05-.439.466-1.17.742-1.271-.02.063-.035.112-.053.16-.043.116-.097.227-.13.345a1.901 1.901 0 00-.05.82c.033.212.09.416.204.6.147.236.346.407.62.465.11.023.225.014.338.018a.576.576 0 00.386-.131c.164-.128.282-.292.366-.481.168-.375.24-.777.309-1.179.05-.296.093-.594.133-.893.039-.281.071-.563.104-.845.026-.232.048-.464.074-.696.024-.228.052-.455.076-.683.024-.227.047-.455.069-.683.013-.14.022-.28.034-.42l.037-.417c.022-.25.041-.5.065-.748.008-.082-.02-.132-.09-.177a2.46 2.46 0 01-.492-.418c-.1-.109-.188-.228-.282-.342-.035-.042-.056-.097-.116-.118a2.084 2.084 0 00.275.597c.06.092.131.176.196.265.063.086.182.115.234.226-.028.003-.046.01-.06.006a4.74 4.74 0 01-.22-.057 2.71 2.71 0 01-1.287-.819c-.435-.487-.656-1.076-.71-1.723a5.206 5.206 0 01.014-1.06c.072-.602.22-1.186.45-1.745.155-.376.338-.741.526-1.102.205-.393.466-.75.765-1.076.512-.559 1.104-1.024 1.726-1.448.717-.49 1.478-.898 2.277-1.233C8.244.828 8.767.632 9.31.494c.655-.166 1.31-.33 1.982-.415.229-.03.458-.058.688-.07z"/>
                                        </svg>
                                    </div>
                                @elseif ($key === 'openclaw')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-500/15 border border-red-500/30 text-red-500 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M12 2.568c-6.33 0-9.495 5.275-9.495 9.495 0 4.22 3.165 8.44 6.33 9.494v2.11h2.11v-2.11s1.055.422 2.11 0v2.11h2.11v-2.11c3.165-1.055 6.33-5.274 6.33-9.494S18.33 2.568 12 2.568z" fill="url(#ai-claw-grad-{{ $key }})"/>
                                            <path d="M3.56 9.953C.396 8.898-.66 11.008.396 13.118c1.055 2.11 3.164 1.055 4.22-1.055.632-1.477 0-2.11-1.056-2.11z" fill="url(#ai-claw-grad-{{ $key }})"/>
                                            <path d="M20.44 9.953c3.164-1.055 4.22 1.055 3.164 3.165-1.055 2.11-3.164 1.055-4.22-1.055-.632-1.477 0-2.11 1.056-2.11z" fill="url(#ai-claw-grad-{{ $key }})"/>
                                            <path d="M5.507 1.875c.476-.285 1.036-.233 1.615.037.577.27 1.223.774 1.937 1.488a.316.316 0 01-.447.447c-.693-.693-1.279-1.138-1.757-1.361-.475-.222-.795-.205-1.022-.069a.317.317 0 01-.326-.542zM16.877 1.913c.58-.27 1.14-.323 1.616-.038a.317.317 0 01-.326.542c-.227-.136-.547-.153-1.022.069-.478.223-1.064.668-1.756 1.361a.316.316 0 11-.448-.447c.714-.714 1.36-1.218 1.936-1.487z" fill="#FF4D4D"/>
                                            <path d="M8.835 9.109a1.266 1.266 0 100-2.532 1.266 1.266 0 000 2.532zM15.165 9.109a1.266 1.266 0 100-2.532 1.266 1.266 0 000 2.532z" fill="#050810"/>
                                            <path d="M9.046 8.16a.527.527 0 100-1.056.527.527 0 000 1.055zM15.376 8.16a.527.527 0 100-1.055.527.527 0 000 1.054z" fill="#00E5CC"/>
                                            <defs>
                                                <linearGradient id="ai-claw-grad-{{ $key }}" x1="-.659" x2="27.023" y1=".458" y2="22.855" gradientUnits="userSpaceOnUse">
                                                    <stop stop-color="#FF4D4D"/>
                                                    <stop offset="1" stop-color="#991B1B"/>
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                    </div>
                                @elseif ($key === 'antigravity')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <mask height="23" id="ai-agy-mask" maskUnits="userSpaceOnUse" width="24" x="0" y="1">
                                                <path d="M21.751 22.607c1.34 1.005 3.35.335 1.508-1.508C17.73 15.74 18.904 1 12.037 1 5.17 1 6.342 15.74.815 21.1c-2.01 2.009.167 2.511 1.507 1.506 5.192-3.517 4.857-9.714 9.715-9.714 4.857 0 4.522 6.197 9.714 9.715z" fill="#fff"/>
                                            </mask>
                                            <g mask="url(#ai-agy-mask)">
                                                <g filter="url(#agy-b1)"><path d="M-1.018-3.992c-.408 3.591 2.686 6.89 6.91 7.37 4.225.48 7.98-2.043 8.387-5.633.408-3.59-2.686-6.89-6.91-7.37-4.225-.479-7.98 2.043-8.387 5.633z" fill="#FFE432"/></g>
                                                <g filter="url(#agy-b2)"><path d="M15.269 7.747c1.058 4.557 5.691 7.374 10.348 6.293 4.657-1.082 7.575-5.653 6.516-10.21-1.058-4.556-5.691-7.374-10.348-6.292-4.657 1.082-7.575 5.653-6.516 10.21z" fill="#FC413D"/></g>
                                                <g filter="url(#agy-b3)"><path d="M-12.443 10.804c1.338 4.703 7.36 7.11 13.453 5.378 6.092-1.733 9.947-6.95 8.61-11.652C8.282-.173 2.26-2.58-3.833-.848-9.925.884-13.78 6.1-12.443 10.804z" fill="#00B95C"/></g>
                                                <g filter="url(#agy-b4)"><path d="M-7.608 14.703c3.352 3.424 9.126 3.208 12.896-.483 3.77-3.69 4.108-9.459.756-12.883C2.69-2.087-3.083-1.871-6.853 1.82c-3.77 3.69-4.108 9.458-.755 12.883z" fill="#00B95C"/></g>
                                                <g filter="url(#agy-b5)"><path d="M9.932 27.617c1.04 4.482 5.384 7.303 9.7 6.3 4.316-1.002 6.971-5.448 5.93-9.93-1.04-4.483-5.384-7.304-9.7-6.301-4.316 1.002-6.971 5.448-5.93 9.93z" fill="#3186FF"/></g>
                                                <g filter="url(#agy-b6)"><path d="M2.572-8.185C.392-3.329 2.778 2.472 7.9 4.771c5.122 2.3 11.042.227 13.222-4.63 2.18-4.855-.205-10.656-5.327-12.955-5.122-2.3-11.042-.227-13.222 4.63z" fill="#FBBC04"/></g>
                                                <g filter="url(#agy-b7)"><path d="M-3.267 38.686c-5.277-2.072 3.742-19.117 5.984-24.83 2.243-5.712 8.34-8.664 13.616-6.592 5.278 2.071 11.533 13.482 9.29 19.195-2.242 5.713-23.613 14.298-28.89 12.227z" fill="#3186FF"/></g>
                                                <g filter="url(#agy-b8)"><path d="M28.71 17.471c-1.413 1.649-5.1.808-8.236-1.878-3.135-2.687-4.531-6.201-3.118-7.85 1.412-1.649 5.1-.808 8.235 1.878s4.532 6.2 3.119 7.85z" fill="#749BFF"/></g>
                                                <g filter="url(#agy-b9)"><path d="M18.163 9.077c5.81 3.93 12.502 4.19 14.946.577 2.443-3.612-.287-9.727-6.098-13.658-5.81-3.931-12.502-4.19-14.946-.577-2.443 3.612.287 9.727 6.098 13.658z" fill="#FC413D"/></g>
                                                <g filter="url(#agy-b10)"><path d="M-.915 2.684c-1.44 3.473-.97 6.967 1.05 7.804 2.02.837 4.824-1.3 6.264-4.772 1.44-3.473.97-6.967-1.05-7.804-2.02-.837-4.824 1.3-6.264 4.772z" fill="#FFEE48"/></g>
                                            </g>
                                        </svg>
                                    </div>
                                @elseif ($key === 'opencode')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-zinc-100 dark:bg-zinc-800 dark:text-white border border-zinc-700/60 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 512 512" fill="none">
                                            <path d="M320 224V352H192V224H320Z" fill="#71717A"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M384 416H128V96H384V416ZM320 160H192V352H320V160Z" fill="currentColor"/>
                                        </svg>
                                    </div>
                                @elseif ($key === 'codex')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#6366F1]/15 border border-[#6366F1]/30 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M8.086.457a6.105 6.105 0 013.046-.415c1.333.153 2.521.72 3.564 1.7a.117.117 0 00.107.029c1.408-.346 2.762-.224 4.061.366l.063.03.154.076c1.357.703 2.33 1.77 2.918 3.198.278.679.418 1.388.421 2.126a5.655 5.655 0 01-.18 1.631.167.167 0 00.04.155 5.982 5.982 0 011.578 2.891c.385 1.901-.01 3.615-1.183 5.14l-.182.22a6.063 6.063 0 01-2.934 1.851.162.162 0 00-.108.102c-.255.736-.511 1.364-.987 1.992-1.199 1.582-2.962 2.462-4.948 2.451-1.583-.008-2.986-.587-4.21-1.736a.145.145 0 00-.14-.032c-.518.167-1.04.191-1.604.185a5.924 5.924 0 01-2.595-.622 6.058 6.058 0 01-2.146-1.781c-.203-.269-.404-.522-.551-.821a7.74 7.74 0 01-.495-1.283 6.11 6.11 0 01-.017-3.064.166.166 0 00.008-.074.115.115 0 00-.037-.064 5.958 5.958 0 01-1.38-2.202 5.196 5.196 0 01-.333-1.589 6.915 6.915 0 01.188-2.132c.45-1.484 1.309-2.648 2.577-3.493.282-.188.55-.334.802-.438.286-.12.573-.22.861-.304a.129.129 0 00.087-.087A6.016 6.016 0 015.635 2.31C6.315 1.464 7.132.846 8.086.457zm-.804 7.85a.848.848 0 00-1.473.842l1.694 2.965-1.688 2.848a.849.849 0 001.46.864l1.94-3.272a.849.849 0 00.007-.854l-1.94-3.393zm5.446 6.24a.849.849 0 000 1.695h4.848a.849.849 0 000-1.696h-4.848z" fill="url(#ai-codex-grad-{{ $key }})"/>
                                            <defs>
                                                <linearGradient id="ai-codex-grad-{{ $key }}" x1="0%" y1="0%" x2="100%" y2="100%">
                                                    <stop offset="0%" stop-color="#6366F1"/>
                                                    <stop offset="100%" stop-color="#A855F7"/>
                                                </linearGradient>
                                            </defs>
                                        </svg>
                                    </div>
                                @elseif ($key === 'windsurf')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#09B6A2] text-[#0B100F] shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 1024 1024" fill="currentColor">
                                            <path d="M897.246 286.869H889.819C850.735 286.808 819.017 318.46 819.017 357.539V515.589C819.017 547.15 792.93 572.716 761.882 572.716C743.436 572.716 725.02 563.433 714.093 547.85L552.673 317.304C539.28 298.16 517.486 286.747 493.895 286.747C457.094 286.747 423.976 318.034 423.976 356.657V515.619C423.976 547.181 398.103 572.746 366.842 572.746C348.335 572.746 329.949 563.463 319.021 547.881L138.395 289.882C134.316 284.038 125.154 286.93 125.154 294.052V431.892C125.154 438.862 127.285 445.619 131.272 451.34L309.037 705.2C319.539 720.204 335.033 731.344 352.9 735.392C397.616 745.557 438.77 711.135 438.77 667.278V508.406C438.77 476.845 464.339 451.279 495.904 451.279H495.995C515.02 451.279 532.857 460.562 543.785 476.145L705.235 706.661C718.659 725.835 739.327 737.218 763.983 737.218C801.606 737.218 833.841 705.9 833.841 667.308V508.376C833.841 476.815 859.41 451.249 890.975 451.249H897.276C901.233 451.249 904.43 448.053 904.43 444.097V294.021C904.43 290.065 901.233 286.869 897.276 286.869H897.246Z"/>
                                        </svg>
                                    </div>
                                @elseif ($key === 'cline')
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-500 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
                                            <path d="M17.035 3.991c2.75 0 4.98 2.24 4.98 5.003v1.667l1.45 2.896a1.01 1.01 0 01-.002.909l-1.448 2.864v1.668c0 2.762-2.23 5.002-4.98 5.002H7.074c-2.751 0-4.98-2.24-4.98-5.002V17.33l-1.48-2.855a1.01 1.01 0 01-.003-.927l1.482-2.887V8.994c0-2.763 2.23-5.003 4.98-5.003h9.962zM8.265 9.6a2.274 2.274 0 00-2.274 2.274v4.042a2.274 2.274 0 004.547 0v-4.042A2.274 2.274 0 008.265 9.6zm7.326 0a2.274 2.274 0 00-2.274 2.274v4.042a2.274 2.274 0 104.548 0v-4.042A2.274 2.274 0 0015.59 9.6z"/>
                                            <path d="M12.054 5.558a2.779 2.779 0 100-5.558 2.779 2.779 0 000 5.558z"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 border border-sky-500/30 text-sky-500 shadow-xs">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
                                            <path d="M19.245 5.364c1.322 1.36 1.877 3.216 2.11 5.817.622 0 1.2.135 1.592.654l.73.964c.21.278.323.61.323.955v2.62c0 .339-.173.669-.453.868C20.239 19.602 16.157 21.5 12 21.5c-4.6 0-9.205-2.583-11.547-4.258-.28-.2-.452-.53-.453-.868v-2.62c0-.345.113-.679.321-.956l.73-.963c.392-.517.974-.654 1.593-.654l.029-.297c.25-2.446.81-4.213 2.082-5.52 2.461-2.54 5.71-2.851 7.146-2.864h.198c1.436.013 4.685.323 7.146 2.864zm-7.244 4.328c-.284 0-.613.016-.962.05-.123.447-.305.85-.57 1.108-1.05 1.023-2.316 1.18-2.994 1.18-.638 0-1.306-.13-1.851-.464-.516.165-1.012.403-1.044.996a65.882 65.882 0 00-.063 2.884l-.002.48c-.002.563-.005 1.126-.013 1.69.002.326.204.63.51.765 2.482 1.102 4.83 1.657 6.99 1.657 2.156 0 4.504-.555 6.985-1.657a.854.854 0 00.51-.766c.03-1.682.006-3.372-.076-5.053-.031-.596-.528-.83-1.046-.996-.546.333-1.212.464-1.85.464-.677 0-1.942-.157-2.993-1.18-.266-.258-.447-.661-.57-1.108-.32-.032-.64-.049-.96-.05zm-2.525 4.013c.539 0 .976.426.976.95v1.753c0 .525-.437.95-.976.95a.964.964 0 01-.976-.95v-1.752c0-.525.437-.951.976-.951zm5 0c.539 0 .976.426.976.95v1.753c0 .525-.437.95-.976.95a.964.964 0 01-.976-.95v-1.752c0-.525.437-.951.976-.951zM7.635 5.087c-1.05.102-1.935.438-2.385.906-.975 1.037-.765 3.668-.21 4.224.405.394 1.17.657 1.995.657h.09c.649-.013 1.785-.176 2.73-1.11.435-.41.705-1.433.675-2.47-.03-.834-.27-1.52-.63-1.813-.39-.336-1.275-.482-2.265-.394zm6.465.394c-.36.292-.6.98-.63 1.813-.03 1.037.24 2.06.675 2.47.968.957 2.136 1.104 2.776 1.11h.044c.825 0 1.59-.263 1.995-.657.555-.556.765-3.187-.21-4.224-.45-.468-1.335-.804-2.385-.906-.99-.088-1.875.058-2.265.394zM12 7.615c-.24 0-.525.015-.84.044.03.16.045.336.06.526l-.001.159a2.94 2.94 0 01-.014.25c.225-.022.425-.027.612-.028h.366c.187 0 .387.006.612.028-.015-.146-.015-.277-.015-.409.015-.19.03-.365.06-.526a9.29 9.29 0 00-.84-.044z"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <h3 class="font-bold text-sm sm:text-base text-foreground truncate">{{ $agent['name'] }}</h3>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $agent['tool'] }}</p>
                                </div>
                            </div>
                            <span class="rounded-md border border-border bg-muted/60 px-2 py-0.5 text-[10px] font-semibold text-muted-foreground shrink-0">
                                {{ $agent['badge'] }}
                            </span>
                        </div>

                        <p class="text-xs leading-relaxed text-muted-foreground line-clamp-2 mt-2">
                            {{ $agent['desc'] }}
                        </p>

                        {{-- Metadata File Target --}}
                        <div class="mt-3.5 flex items-center gap-1.5 text-[11px] font-mono text-foreground/80 bg-muted/50 px-2.5 py-1 rounded-lg border border-border/60">
                            <i class="bi bi-file-earmark-code text-primary"></i>
                            <span class="text-muted-foreground">Target:</span>
                            <span class="font-semibold text-foreground truncate">{{ $agent['file'] }}</span>
                        </div>
                    </div>

                    {{-- Action Footer: Tombol Utama Salin Prompt Instalasi & Unduh Berkas --}}
                    <div class="mt-5 pt-3.5 border-t border-border/60 flex items-center gap-1.5">
                        <button type="button"
                                @click="salinTeks(@js($agent['prompt']), 'agent_{{ $key }}')"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all active:scale-98 cursor-pointer">
                            <template x-if="disalinKey === 'agent_{{ $key }}'">
                                <span class="inline-flex items-center gap-1 text-emerald-100">
                                    <i class="bi bi-check2 text-sm font-bold"></i>
                                    <span>Tersalin!</span>
                                </span>
                            </template>
                            <template x-if="disalinKey !== 'agent_{{ $key }}'">
                                <span class="inline-flex items-center gap-1.5 truncate">
                                    <i class="bi bi-clipboard"></i>
                                    <span>Salin Prompt Instalasi</span>
                                </span>
                            </template>
                        </button>

                        <a href="{{ route('ai.agent.download', $key) }}"
                           download
                           class="inline-flex items-center justify-center rounded-xl border border-input bg-card p-2 text-xs text-muted-foreground hover:text-primary hover:bg-muted transition-colors cursor-pointer shadow-2xs"
                           title="Unduh berkas {{ $agent['file'] }}">
                            <i class="bi bi-download"></i>
                        </a>

                        <button type="button"
                                @click="previewAgent = (previewAgent === '{{ $key }}' ? null : '{{ $key }}')"
                                class="inline-flex items-center justify-center rounded-xl border border-input bg-card p-2 text-xs text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer shadow-2xs"
                                title="Lihat isi prompt">
                            <i class="bi" :class="previewAgent === '{{ $key }}' ? 'bi-chevron-up' : 'bi-eye'"></i>
                        </button>
                    </div>

                    {{-- Accordion Preview Prompt --}}
                    <div x-show="previewAgent === '{{ $key }}'" x-collapse x-cloak class="mt-3 pt-3 border-t border-border/40">
                        <div class="relative rounded-lg bg-[var(--code-chrome)] p-3 text-[11px] font-mono text-[var(--code-foreground)] border border-border">
                            <pre class="whitespace-pre-wrap break-words max-h-48 overflow-y-auto">{{ $agent['prompt'] }}</pre>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== 4 LANGKAH UTAMA INTEGRASI (BUKU PANDUAN) ===================== --}}
<section id="langkah-integrasi" class="py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

        {{-- Judul Seksi --}}
        <div class="max-w-3xl mb-8 sm:mb-10">
            <span class="text-xs font-bold uppercase tracking-wider text-primary">Panduan Langkah Demi Langkah</span>
            <h2 class="muncul mt-1.5 text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-foreground">
                4 Langkah Menghubungkan AI ke WA Gateway
            </h2>
            <p class="muncul mt-2 text-xs sm:text-sm text-muted-foreground leading-relaxed" style="--tunda: 40ms">
                Gunakan tab buku di bawah untuk membuka lembar langkah integrasi yang Anda butuhkan, mulai dari instalasi pustaka SDK resmi, konfigurasi environment, skenario prompt siap pakai, hingga pengujian via CLI terminal.
            </p>
        </div>

        {{-- Container Buku: Tab Pembatas Buku yang Menyambung ke Halaman Card --}}
        <div class="muncul" style="--tunda: 70ms">
            {{-- Tab Header (Menyambung dengan bagian atas Card seperti Tab Buku) --}}
            <div class="flex items-end overflow-x-auto no-scrollbar gap-1.5 sm:gap-2 px-2 sm:px-6 relative z-20 -mb-[1px]">
                {{-- Tab Langkah 1 --}}
                <button type="button"
                        @click="langkahAktif = 1"
                        class="group flex items-center gap-2.5 sm:gap-3 rounded-t-2xl border transition-all text-left shrink-0 cursor-pointer"
                        :class="langkahAktif === 1
                            ? 'bg-card text-foreground border-border border-b-card border-t-2 border-t-primary px-4 sm:px-6 py-3 sm:py-3.5 shadow-xs font-semibold'
                            : 'bg-muted/40 text-muted-foreground border-transparent hover:bg-muted/70 hover:text-foreground px-3.5 sm:px-5 py-2.5 sm:py-3'">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-all"
                          :class="langkahAktif === 1 ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted text-muted-foreground group-hover:bg-background'">
                        1
                    </span>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-bold"
                             :class="langkahAktif === 1 ? 'text-primary' : 'text-muted-foreground/70'">Langkah 1</div>
                        <div class="text-xs sm:text-sm font-bold truncate">Install SDK Resmi</div>
                    </div>
                </button>

                {{-- Tab Langkah 2 --}}
                <button type="button"
                        @click="langkahAktif = 2"
                        class="group flex items-center gap-2.5 sm:gap-3 rounded-t-2xl border transition-all text-left shrink-0 cursor-pointer"
                        :class="langkahAktif === 2
                            ? 'bg-card text-foreground border-border border-b-card border-t-2 border-t-emerald-500 px-4 sm:px-6 py-3 sm:py-3.5 shadow-xs font-semibold'
                            : 'bg-muted/40 text-muted-foreground border-transparent hover:bg-muted/70 hover:text-foreground px-3.5 sm:px-5 py-2.5 sm:py-3'">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-all"
                          :class="langkahAktif === 2 ? 'bg-emerald-500 text-white shadow-xs' : 'bg-muted text-muted-foreground group-hover:bg-background'">
                        2
                    </span>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-bold"
                             :class="langkahAktif === 2 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground/70'">Langkah 2</div>
                        <div class="text-xs sm:text-sm font-bold truncate">Hubungkan AI Gateway</div>
                    </div>
                </button>

                {{-- Tab Langkah 3 --}}
                <button type="button"
                        @click="langkahAktif = 3"
                        class="group flex items-center gap-2.5 sm:gap-3 rounded-t-2xl border transition-all text-left shrink-0 cursor-pointer"
                        :class="langkahAktif === 3
                            ? 'bg-card text-foreground border-border border-b-card border-t-2 border-t-amber-500 px-4 sm:px-6 py-3 sm:py-3.5 shadow-xs font-semibold'
                            : 'bg-muted/40 text-muted-foreground border-transparent hover:bg-muted/70 hover:text-foreground px-3.5 sm:px-5 py-2.5 sm:py-3'">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-all"
                          :class="langkahAktif === 3 ? 'bg-amber-500 text-white shadow-xs' : 'bg-muted text-muted-foreground group-hover:bg-background'">
                        3
                    </span>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-bold"
                             :class="langkahAktif === 3 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground/70'">Langkah 3</div>
                        <div class="text-xs sm:text-sm font-bold truncate">Tulis Prompt Pertama</div>
                    </div>
                </button>

                {{-- Tab Langkah 4 --}}
                <button type="button"
                        @click="langkahAktif = 4"
                        class="group flex items-center gap-2.5 sm:gap-3 rounded-t-2xl border transition-all text-left shrink-0 cursor-pointer"
                        :class="langkahAktif === 4
                            ? 'bg-card text-foreground border-border border-b-card border-t-2 border-t-indigo-500 px-4 sm:px-6 py-3 sm:py-3.5 shadow-xs font-semibold'
                            : 'bg-muted/40 text-muted-foreground border-transparent hover:bg-muted/70 hover:text-foreground px-3.5 sm:px-5 py-2.5 sm:py-3'">
                    <span class="flex h-6 w-6 sm:h-7 sm:w-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold transition-all"
                          :class="langkahAktif === 4 ? 'bg-indigo-500 text-white shadow-xs' : 'bg-muted text-muted-foreground group-hover:bg-background'">
                        4
                    </span>
                    <div class="min-w-0">
                        <div class="text-[10px] uppercase tracking-wider font-bold"
                             :class="langkahAktif === 4 ? 'text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground/70'">Langkah 4</div>
                        <div class="text-xs sm:text-sm font-bold truncate">CLI & Terminal</div>
                    </div>
                </button>
            </div>

            {{-- Lembar Card Utama (The Book Sheet) --}}
            <div class="rounded-2xl sm:rounded-3xl border border-border bg-card p-6 sm:p-8 lg:p-10 shadow-sm relative z-10 flex flex-col justify-between min-h-[480px]">
                <div>
                    {{-- ===================== KONTEN LANGKAH 1 ===================== --}}
                    <div x-show="langkahAktif === 1" x-transition.opacity.duration.200ms>
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 pb-6 border-b border-border/70">
                            <div class="max-w-2xl">
                                <div class="inline-flex items-center gap-2 rounded-lg bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary mb-2">
                                    <i class="bi bi-box-seam"></i>
                                    <span>Langkah 1 &middot; Client Libraries</span>
                                </div>
                                <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                    Jalankan Install Prompt SDK Resmi (Client Libraries)
                                </h2>
                                <p class="mt-2 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                    Pilih bahasa atau framework proyek Anda. Salin prompt di bawah ke obrolan AI agent Anda agar AI langsung membuatkan class service atau modul client yang rapi, lengkap dengan penanganan galat dan validasi nomor.
                                </p>
                            </div>

                            {{-- Tab Switcher Bahasa SDK --}}
                            <div class="flex flex-wrap items-center gap-1.5 p-1 rounded-xl bg-muted/60 border border-border shrink-0">
                                @foreach ($sdkPrompts as $sdkKey => $sdk)
                                    <button type="button"
                                            @click="tabSdk = '{{ $sdkKey }}'"
                                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer"
                                            :class="tabSdk === '{{ $sdkKey }}' ? 'bg-background text-foreground shadow-xs border border-border' : 'text-muted-foreground hover:text-foreground'">
                                        {{ $sdk['title'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tampilan Prompt SDK Sesuai Tab --}}
                        @foreach ($sdkPrompts as $sdkKey => $sdk)
                            <div x-show="tabSdk === '{{ $sdkKey }}'" x-cloak class="mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-foreground">{{ $sdk['lang'] }}</span>
                                        <span class="rounded bg-primary/10 border border-primary/20 px-2 py-0.5 text-[11px] font-semibold text-primary">{{ $sdk['badge'] }}</span>
                                    </div>
                                    <button type="button"
                                            @click="salinTeks(@js($sdk['prompt']), 'sdk_{{ $sdkKey }}')"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                                        <i class="bi" :class="disalinKey === 'sdk_{{ $sdkKey }}' ? 'bi-check2 text-emerald-200' : 'bi-clipboard'"></i>
                                        <span x-text="disalinKey === 'sdk_{{ $sdkKey }}' ? 'Prompt SDK Tersalin!' : 'Salin Prompt Instalasi SDK'">Salin Prompt Instalasi SDK</span>
                                    </button>
                                </div>

                                <p class="text-xs text-muted-foreground mb-3">{{ $sdk['desc'] }}</p>

                                <div class="rounded-xl border border-border/80 bg-[var(--code-chrome)] shadow-inner overflow-hidden">
                                    <pre class="overflow-x-auto p-4 sm:p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)] whitespace-pre-wrap break-words">{{ $sdk['prompt'] }}</pre>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ===================== KONTEN LANGKAH 2 ===================== --}}
                    <div x-show="langkahAktif === 2" x-cloak x-transition.opacity.duration.200ms>
                        <div class="max-w-3xl mb-8">
                            <div class="inline-flex items-center gap-2 rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400 mb-2">
                                <i class="bi bi-link-45deg"></i>
                                <span>Langkah 2 &middot; Kredensial & Endpoint</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                Hubungkan AI ke WA Gateway Pesanvo
                            </h2>
                            <p class="mt-2 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                Pastikan AI Anda memiliki informasi kredensial dan endpoint yang benar. Cukup simpan di berkas environment lokal proyek Anda:
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {{-- Langkah 2.1 --}}
                            <div class="rounded-2xl border border-border/80 bg-background/60 p-5 space-y-3">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20 text-primary text-[11px]">1</span>
                                    <span>Dapatkan API Key</span>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Masuk ke Dashboard Pesanvo, buka menu <strong>API Keys</strong>, dan terbitkan kunci baru dengan hak akses yang Anda butuhkan.
                                </p>
                                <a href="{{ route('docs.show', 'api-key') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                    <span>Pelajari Scoped Key</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>

                            {{-- Langkah 2.2 --}}
                            <div class="rounded-2xl border border-border/80 bg-background/60 p-5 space-y-3">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20 text-primary text-[11px]">2</span>
                                    <span>Konfigurasi Variabel .env</span>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Tambahkan parameter berikut ke file <code class="bg-muted px-1.5 py-0.5 rounded font-mono text-foreground">.env</code> lokal agar AI tidak pernah melakukan hardcode kunci rahasia.
                                </p>
                                <button type="button"
                                        @click="salinTeks('WA_GATEWAY_URL=https://wa.pesanvo.id\nWA_GATEWAY_KEY=vwa_live_xxxxxxxxxxxxxxxx\nWA_GATEWAY_SESSION=', 'env_cfg')"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer">
                                    <i class="bi" :class="disalinKey === 'env_cfg' ? 'bi-check2' : 'bi-clipboard'"></i>
                                    <span x-text="disalinKey === 'env_cfg' ? 'Tersalin!' : 'Salin Snippet .env'">Salin Snippet .env</span>
                                </button>
                            </div>

                            {{-- Langkah 2.3 --}}
                            <div class="rounded-2xl border border-border/80 bg-background/60 p-5 space-y-3">
                                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/20 text-primary text-[11px]">3</span>
                                    <span>Tautkan Nomor WhatsApp</span>
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Scan QR Code nomor WhatsApp Anda di Dashboard. Begitu tersambung, AI dapat langsung mengirim pesan transaksional tanpa henti.
                                </p>
                                <a href="{{ route('docs.show', 'menautkan-nomor') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                                    <span>Panduan Multi-Nomor</span>
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Box Spesifikasi Header & Payload --}}
                        <div class="mt-8 rounded-2xl border border-border/80 bg-muted/30 p-5">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-foreground">Spesifikasi Endpoint REST API Utama</span>
                                <span class="font-mono text-xs text-primary font-semibold">Base: https://wa.pesanvo.id/api/v1</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs font-mono">
                                <div class="p-2.5 rounded-xl bg-card border border-border">
                                    <div class="text-[10px] text-muted-foreground uppercase">Kirim Teks</div>
                                    <div class="font-bold text-foreground mt-0.5">POST /messages/text</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-card border border-border">
                                    <div class="text-[10px] text-muted-foreground uppercase">Kirim Dokumen / Media</div>
                                    <div class="font-bold text-foreground mt-0.5">POST /messages/media</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-card border border-border">
                                    <div class="text-[10px] text-muted-foreground uppercase">Kirim Template</div>
                                    <div class="font-bold text-foreground mt-0.5">POST /messages/template</div>
                                </div>
                                <div class="p-2.5 rounded-xl bg-card border border-border">
                                    <div class="text-[10px] text-muted-foreground uppercase">Broadcast Massal</div>
                                    <div class="font-bold text-foreground mt-0.5">POST /messages/bulk</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ===================== KONTEN LANGKAH 3 ===================== --}}
                    <div x-show="langkahAktif === 3" x-cloak x-transition.opacity.duration.200ms>
                        <div class="max-w-3xl mb-8">
                            <div class="inline-flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 text-xs font-bold text-amber-600 dark:text-amber-400 mb-2">
                                <i class="bi bi-chat-left-text"></i>
                                <span>Langkah 3 &middot; Skenario Siap Pakai</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                Tulis Prompt Pertama Kamu
                            </h2>
                            <p class="mt-2 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                Gunakan template skenario nyata berikut untuk meminta AI membuatkan logika aplikasi spesifik. Klik <strong>Salin Prompt</strong> dan langsung tempelkan ke AI Anda:
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach ($firstPrompts as $pKey => $promptItem)
                                <div class="rounded-2xl border border-border/80 bg-background/80 p-5 flex flex-col justify-between hover:border-primary/40 transition-all">
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-2.5">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <i class="bi {{ $promptItem['icon'] }} text-primary text-base"></i>
                                                <h3 class="font-bold text-sm text-foreground truncate">{{ $promptItem['title'] }}</h3>
                                            </div>
                                            <span class="rounded bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground shrink-0">{{ $promptItem['tag'] }}</span>
                                        </div>
                                        <div class="rounded-xl border border-border bg-[var(--code-chrome)] p-3.5 mt-3">
                                            <pre class="font-mono text-xs leading-relaxed text-[var(--code-foreground)] whitespace-pre-wrap break-words">{{ $promptItem['prompt'] }}</pre>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-t border-border/60 flex items-center justify-end">
                                        <button type="button"
                                                @click="salinTeks(@js($promptItem['prompt']), 'fp_{{ $pKey }}')"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-muted border border-border px-3 py-1.5 text-xs font-semibold text-foreground hover:bg-muted/80 transition-all cursor-pointer">
                                            <i class="bi" :class="disalinKey === 'fp_{{ $pKey }}' ? 'bi-check2 text-emerald-500' : 'bi-clipboard'"></i>
                                            <span x-text="disalinKey === 'fp_{{ $pKey }}' ? 'Prompt Tersalin!' : 'Salin Prompt Skenario Ini'">Salin Prompt Skenario Ini</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ===================== KONTEN LANGKAH 4 ===================== --}}
                    <div x-show="langkahAktif === 4" x-cloak x-transition.opacity.duration.200ms>
                        <div class="max-w-3xl mb-8">
                            <div class="inline-flex items-center gap-2 rounded-lg bg-indigo-500/10 px-2.5 py-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 mb-2">
                                <i class="bi bi-terminal"></i>
                                <span>Langkah 4 &middot; Terminal & Agent CLI</span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                CLI (Command Line Interface & Terminal Testing)
                            </h2>
                            <p class="mt-2 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                                Uji pengiriman langsung dari baris perintah terminal Anda atau biarkan agent CLI (seperti Claude Code, Hermes CLI, atau bash runner) menjalankannya secara deterministik:
                            </p>
                        </div>

                        <div class="space-y-6">
                            @foreach ($cliSnippets as $cliKey => $cli)
                                <div class="rounded-2xl border border-border/80 bg-[var(--code-chrome)] shadow-inner overflow-hidden">
                                    <div class="flex items-center justify-between border-b border-white/10 bg-black/40 px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#ff5f56]"></span>
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#ffbd2e]"></span>
                                            <span class="h-2.5 w-2.5 rounded-full bg-[#27c93f]"></span>
                                            <span class="ml-2 font-mono text-xs font-semibold text-white/90">{{ $cli['title'] }}</span>
                                        </div>
                                        <button type="button"
                                                @click="salinTeks(@js($cli['command']), 'cli_{{ $cliKey }}')"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-white/15 bg-white/10 px-2.5 py-1 text-xs font-semibold text-white hover:bg-white/20 transition-all cursor-pointer">
                                            <i class="bi" :class="disalinKey === 'cli_{{ $cliKey }}' ? 'bi-check2 text-emerald-400' : 'bi-clipboard'"></i>
                                            <span x-text="disalinKey === 'cli_{{ $cliKey }}' ? 'Perintah Tersalin!' : 'Salin Perintah CLI'">Salin Perintah CLI</span>
                                        </button>
                                    </div>
                                    <pre class="p-4 sm:p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)] overflow-x-auto whitespace-pre-wrap break-words">{{ $cli['command'] }}</pre>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Navigasi Bawah Lembar Buku --}}
                <div class="mt-10 pt-6 border-t border-border/70 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <button type="button"
                                x-show="langkahAktif > 1"
                                @click="langkahAktif--; window.location.hash = '#langkah-integrasi'"
                                class="inline-flex items-center gap-2 rounded-xl border border-border bg-muted/60 px-4 py-2.5 text-xs sm:text-sm font-semibold text-foreground hover:bg-muted transition-all cursor-pointer">
                            <i class="bi bi-arrow-left"></i>
                            <span>Langkah Sebelumnya</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <span>Halaman</span>
                        <span class="font-bold text-foreground" x-text="langkahAktif"></span>
                        <span>dari 4</span>
                        <div class="flex items-center gap-1.5 ml-2">
                            <button type="button" @click="langkahAktif = 1" class="h-1.5 rounded-full transition-all cursor-pointer" :class="langkahAktif === 1 ? 'w-6 bg-primary' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/60'"></button>
                            <button type="button" @click="langkahAktif = 2" class="h-1.5 rounded-full transition-all cursor-pointer" :class="langkahAktif === 2 ? 'w-6 bg-emerald-500' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/60'"></button>
                            <button type="button" @click="langkahAktif = 3" class="h-1.5 rounded-full transition-all cursor-pointer" :class="langkahAktif === 3 ? 'w-6 bg-amber-500' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/60'"></button>
                            <button type="button" @click="langkahAktif = 4" class="h-1.5 rounded-full transition-all cursor-pointer" :class="langkahAktif === 4 ? 'w-6 bg-indigo-500' : 'w-2 bg-muted-foreground/30 hover:bg-muted-foreground/60'"></button>
                        </div>
                    </div>

                    <div>
                        <button type="button"
                                x-show="langkahAktif < 4"
                                @click="langkahAktif++; window.location.hash = '#langkah-integrasi'"
                                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                            <span>Langkah Selanjutnya</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                        <a href="{{ route('docs.show', 'integrasi-ai-agent') }}"
                           x-show="langkahAktif === 4"
                           class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">
                            <span>Buka Dokumentasi Lengkap</span>
                            <i class="bi bi-journal-text"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- ===================== CALL TO ACTION ===================== --}}
<section class="border-t border-border bg-primary text-primary-foreground">
    <div class="mx-auto max-w-4xl px-6 py-16 text-center sm:px-8 lg:py-20">
        <h2 class="muncul text-2xl sm:text-3xl lg:text-4xl font-bold tracking-tight">
            Mulai Integrasi AI Agent WhatsApp Sekarang
        </h2>
        <p class="muncul mx-auto mt-4 max-w-xl text-xs sm:text-sm text-primary-foreground/85 leading-relaxed" style="--tunda: 60ms">
            Daftar akun gratis, dapatkan API Key, dan hubungkan AI Anda ke gateway dalam hitungan detik. Tanpa kontrak yang mengikat.
        </p>
        <div class="muncul mt-8 flex flex-wrap justify-center gap-3" style="--tunda: 110ms">
            <a href="{{ route('register') }}" class="rounded-xl bg-primary-foreground px-6 py-3 text-sm font-semibold text-primary shadow-sm hover:opacity-90 transition-all">
                Daftar & Buat API Key
            </a>
            <a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="rounded-xl border border-primary-foreground/30 px-6 py-3 text-sm font-medium hover:bg-primary-foreground/10 transition-all">
                Buka Dokumentasi Lengkap
            </a>
        </div>
    </div>
</section>

@include('partials.footer')

<script>
    (function () {
        var blok = document.querySelectorAll('.muncul');
        if (! document.documentElement.classList.contains('animasi-muncul')) {
            blok.forEach(function (el) { el.classList.add('terlihat'); });
            return;
        }
        var pengamat = new IntersectionObserver(function (entri) {
            entri.forEach(function (e) {
                if (! e.isIntersecting) return;
                e.target.classList.add('terlihat');
                pengamat.unobserve(e.target);
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });
        blok.forEach(function (el) { pengamat.observe(el); });
    })();
</script>

</body>
</html>
