<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kompetic — Competition Management Platform</title>
    <meta name="description" content="Kompetic is a modern competition management platform for martial arts and combat sports organisations. Run tournaments, manage registrations, check in competitors with QR codes, and track live scores — powered by AI insights.">
    <meta name="keywords" content="competition management, tournament management software, martial arts competition, combat sports, competitor registration, QR check-in, live scoring, AI insights">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://kompetic.com">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kompetic.com">
    <meta property="og:title" content="Kompetic — Competition Management Platform">
    <meta property="og:description" content="Run tournaments, manage registrations, check in competitors with QR codes, and track live scores — powered by AI insights.">
    <meta property="og:site_name" content="Kompetic">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Kompetic — Competition Management Platform">
    <meta name="twitter:description" content="Run tournaments, manage registrations, check in competitors with QR codes, and track live scores — powered by AI insights.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:       #0f172a;
            --navy-light: #1e293b;
            --indigo:     #6366f1;
            --indigo-lt:  #818cf8;
            --purple:     #a855f7;
            --white:      #ffffff;
            --gray:       #94a3b8;
            --gray-lt:    #cbd5e1;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--navy);
            color: var(--white);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── NAV ── */
        nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(15,23,42,0.92);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(99,102,241,0.15);
            padding: 1rem 2rem;
        }
        .nav-inner { max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; }
        .nav-logo {
            font-size:1.5rem; font-weight:900; letter-spacing:-.5px; text-decoration:none;
            background: linear-gradient(135deg, var(--indigo-lt), var(--purple));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        }
        .nav-right { display:flex; align-items:center; gap:1rem; }
        .badge-beta {
            background: linear-gradient(135deg,rgba(99,102,241,.22),rgba(168,85,247,.22));
            border: 1px solid rgba(99,102,241,.4);
            color: var(--indigo-lt);
            font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
            padding:.25rem .75rem; border-radius:9999px;
        }
        .btn-nav {
            background:var(--indigo); color:white; font-weight:600; font-size:.875rem;
            padding:.5rem 1.25rem; border-radius:8px; text-decoration:none;
            transition:background .2s, transform .1s;
        }
        .btn-nav:hover { background:var(--indigo-lt); transform:translateY(-1px); }

        /* ── MOBILE NAV ── */
        .nav-hamburger { display:none; background:none; border:none; cursor:pointer; padding:.25rem; color:var(--gray-lt); }
        .mobile-menu {
            display:none; flex-direction:column; gap:.5rem;
            padding:1rem 2rem 1.25rem; border-top:1px solid rgba(99,102,241,.12);
            background:rgba(15,23,42,.97);
        }
        .mobile-menu.open { display:flex; }
        .mobile-menu a { color:var(--gray-lt); text-decoration:none; font-size:.95rem; font-weight:500; padding:.35rem 0; transition:color .15s; }
        .mobile-menu a:hover { color:white; }
        @media(max-width:640px) {
            .nav-hamburger { display:block; }
            .nav-links { display:none !important; }
        }
        .nav-links { display:flex; align-items:center; gap:1.25rem; }
        .nav-links a { color:var(--gray); font-size:.875rem; text-decoration:none; transition:color .15s; }
        .nav-links a:hover { color:var(--indigo-lt); }

        /* ── HERO ── */
        .hero {
            position:relative; overflow:hidden;
            padding: 6rem 2rem 5rem; text-align:center;
            background:
                radial-gradient(ellipse at 50% -10%, rgba(99,102,241,.25) 0%, transparent 65%),
                radial-gradient(ellipse at 85% 55%, rgba(168,85,247,.12) 0%, transparent 55%),
                var(--navy);
        }
        .hero::before {
            content:''; position:absolute; inset:0; pointer-events:none;
            background-image:
                linear-gradient(rgba(99,102,241,.06) 1px, transparent 1px),
                linear-gradient(90deg,rgba(99,102,241,.06) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .hero-inner { position:relative; max-width:820px; margin:0 auto; }

        .hero-eyebrow {
            display:inline-flex; align-items:center; gap:.5rem;
            background: linear-gradient(135deg,rgba(99,102,241,.18),rgba(168,85,247,.18));
            border:1px solid rgba(99,102,241,.3); border-radius:9999px;
            padding:.4rem 1.1rem; font-size:.78rem; font-weight:600;
            color:var(--indigo-lt); letter-spacing:.04em; margin-bottom:2rem;
        }
        .hero-eyebrow::before {
            content:''; width:8px; height:8px; border-radius:50%; background:var(--indigo);
            animation: blink 2s infinite;
        }
        @keyframes blink { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.4)} }

        h1 { font-size:clamp(2.4rem,6vw,4rem); font-weight:900; letter-spacing:-.03em; line-height:1.1; margin-bottom:1.5rem; }
        .grad { background:linear-gradient(135deg,var(--indigo-lt),var(--purple)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }

        .hero-sub { font-size:1.1rem; color:var(--gray); max-width:600px; margin:0 auto 2.5rem; line-height:1.75; }

        .hero-ctas { display:flex; align-items:center; justify-content:center; gap:1rem; flex-wrap:wrap; margin-bottom:3rem; }

        .btn-primary {
            display:inline-flex; align-items:center; gap:.5rem;
            background:linear-gradient(135deg,var(--indigo),var(--purple));
            color:white; font-weight:700; font-size:1rem;
            padding:.875rem 2rem; border-radius:10px; text-decoration:none;
            box-shadow:0 4px 24px rgba(99,102,241,.35);
            transition:opacity .2s, transform .15s, box-shadow .2s;
        }
        .btn-primary:hover { opacity:.9; transform:translateY(-2px); box-shadow:0 8px 32px rgba(99,102,241,.5); }

        .btn-secondary {
            display:inline-flex; align-items:center; gap:.5rem;
            background:transparent; border:1.5px solid rgba(99,102,241,.4);
            color:var(--indigo-lt); font-weight:600; font-size:1rem;
            padding:.875rem 2rem; border-radius:10px; text-decoration:none;
            transition:border-color .2s, background .2s, transform .15s;
        }
        .btn-secondary:hover { border-color:var(--indigo); background:rgba(99,102,241,.08); transform:translateY(-2px); }

        /* ── ORG SEARCH ── */
        .org-search-wrap {
            max-width:520px; margin:0 auto;
            background:rgba(30,41,59,.7); border:1px solid rgba(99,102,241,.25);
            border-radius:16px; padding:1.5rem;
        }
        .org-search-label { font-size:.8rem; font-weight:600; color:var(--gray-lt); letter-spacing:.04em; margin-bottom:.75rem; }
        .search-row { display:flex; gap:.5rem; }
        .search-row input {
            flex:1; padding:.7rem 1rem; border-radius:8px;
            border:1px solid rgba(99,102,241,.3); background:rgba(15,23,42,.8);
            color:white; font-size:.95rem; outline:none;
            transition:border-color .2s;
            font-family:inherit;
        }
        .search-row input::placeholder { color:var(--gray); }
        .search-row input:focus { border-color:var(--indigo); }
        .search-row button {
            padding:.7rem 1.25rem; background:var(--indigo); color:white;
            border:none; border-radius:8px; font-size:.9rem; font-weight:600;
            cursor:pointer; font-family:inherit; white-space:nowrap;
            transition:background .2s;
        }
        .search-row button:hover { background:var(--indigo-lt); }

        .org-results { margin-top:1rem; display:flex; flex-direction:column; gap:.5rem; }
        .org-card {
            background:rgba(15,23,42,.6); border:1px solid rgba(99,102,241,.2);
            border-radius:10px; padding:1rem 1.25rem;
            display:flex; align-items:center; justify-content:space-between;
            text-decoration:none; color:inherit;
            transition:border-color .2s, background .2s;
        }
        .org-card:hover { border-color:rgba(99,102,241,.5); background:rgba(99,102,241,.08); }
        .org-name { font-size:1rem; font-weight:600; color:white; }
        .org-url  { font-size:.8rem; color:var(--gray); margin-top:.15rem; }
        .org-arrow { color:var(--indigo-lt); flex-shrink:0; }
        .no-results { text-align:center; color:var(--gray); padding:1.25rem 0; font-size:.9rem; }

        /* ── FEATURES ── */
        .features { padding:6rem 2rem; background:var(--navy-light); }
        .section-head { text-align:center; max-width:640px; margin:0 auto 4rem; }
        .label { display:inline-block; font-size:.72rem; font-weight:700; letter-spacing:.15em; text-transform:uppercase; color:var(--indigo); margin-bottom:.75rem; }
        h2 { font-size:clamp(1.75rem,4vw,2.5rem); font-weight:800; letter-spacing:-.02em; line-height:1.2; margin-bottom:1rem; }
        .sub { color:var(--gray); font-size:1rem; line-height:1.7; }

        .grid-features {
            display:grid; grid-template-columns:repeat(3,1fr); gap:1.5rem;
            max-width:1200px; margin:0 auto;
        }
        @media(max-width:900px){ .grid-features{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:560px){ .grid-features{grid-template-columns:1fr;} }

        .card {
            background:rgba(15,23,42,.6); border:1px solid rgba(99,102,241,.15);
            border-radius:16px; padding:2rem; position:relative; overflow:hidden;
            transition:border-color .2s, transform .2s, box-shadow .2s;
        }
        .card::before {
            content:''; position:absolute; inset:0; pointer-events:none;
            background:radial-gradient(circle at 30% 20%,rgba(99,102,241,.06) 0%,transparent 60%);
        }
        .card:hover { border-color:rgba(99,102,241,.4); transform:translateY(-4px); box-shadow:0 16px 40px rgba(0,0,0,.3); }
        .card.ai {
            border-color:rgba(168,85,247,.3);
            background:linear-gradient(135deg,rgba(99,102,241,.07),rgba(168,85,247,.07));
        }
        .card.ai::before { background:radial-gradient(circle at 30% 20%,rgba(168,85,247,.08) 0%,transparent 60%); }
        .card.ai:hover { border-color:rgba(168,85,247,.6); }

        .icon-wrap {
            width:52px; height:52px; border-radius:12px;
            background:linear-gradient(135deg,rgba(99,102,241,.2),rgba(168,85,247,.2));
            border:1px solid rgba(99,102,241,.3);
            display:flex; align-items:center; justify-content:center; margin-bottom:1.25rem;
        }
        .card.ai .icon-wrap {
            background:linear-gradient(135deg,rgba(168,85,247,.25),rgba(236,72,153,.15));
            border-color:rgba(168,85,247,.4);
        }
        .icon-wrap svg { width:24px; height:24px; }
        .card h3 { font-size:1.05rem; font-weight:700; margin-bottom:.5rem; color:white; }
        .card p  { font-size:.875rem; color:var(--gray); line-height:1.65; }

        .ai-pill {
            display:inline-flex; align-items:center;
            background:linear-gradient(135deg,rgba(168,85,247,.2),rgba(236,72,153,.2));
            border:1px solid rgba(168,85,247,.35); color:#c084fc;
            font-size:.6rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
            padding:.2rem .55rem; border-radius:9999px; margin-left:.45rem; vertical-align:middle;
        }

        /* ── CARD EXAMPLES ── */
        .card-example {
            margin-top:1.1rem; padding:.7rem .85rem;
            background:rgba(0,0,0,.28); border:1px solid rgba(99,102,241,.1);
            border-radius:10px; font-size:.71rem; line-height:1.65; color:var(--gray);
        }
        .ex-label {
            font-size:.58rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
            color:#475569; margin-bottom:.45rem;
        }
        .ex-row {
            display:flex; align-items:center; gap:.45rem;
            padding:.28rem 0; border-bottom:1px solid rgba(255,255,255,.04);
        }
        .ex-row:last-child { border-bottom:none; }
        .ex-badge {
            display:inline-flex; align-items:center; gap:.2rem;
            padding:.1rem .45rem; border-radius:9999px;
            font-size:.58rem; font-weight:700; border:1px solid; white-space:nowrap;
        }
        .ex-green  { background:rgba(34,197,94,.12); color:#86efac; border-color:rgba(34,197,94,.25); }
        .ex-amber  { background:rgba(251,191,36,.1); color:#fde68a; border-color:rgba(251,191,36,.25); }
        .ex-blue   { background:rgba(96,165,250,.12); color:#93c5fd; border-color:rgba(96,165,250,.25); }
        .ex-indigo { background:rgba(99,102,241,.18); color:var(--indigo-lt); border-color:rgba(99,102,241,.3); }
        .ex-gray   { background:rgba(255,255,255,.06); color:#94a3b8; border-color:rgba(255,255,255,.1); }
        .ex-purple { background:rgba(168,85,247,.15); color:#c084fc; border-color:rgba(168,85,247,.25); }
        .ex-val  { color:#e2e8f0; font-weight:600; }
        .ex-dim  { color:#475569; }
        .ex-mono { font-family:'SF Mono','Fira Code',monospace; }
        .ex-prog { display:flex; align-items:center; gap:.5rem; margin-bottom:.45rem; }
        .ex-bar  { flex:1; height:4px; background:rgba(255,255,255,.08); border-radius:2px; overflow:hidden; }
        .ex-bar-fill { height:100%; border-radius:2px; background:#22c55e; }

        /* ── AI FEEDBACK ── */
        .ai-feedback-section {
            padding:6rem 2rem; background:var(--navy);
            position:relative; overflow:hidden;
        }
        .ai-feedback-section::before {
            content:''; position:absolute; top:-20%; right:-10%;
            width:600px; height:600px;
            background:radial-gradient(circle,rgba(168,85,247,.1),transparent 70%); pointer-events:none;
        }
        .ai-feedback-inner { position:relative; max-width:860px; margin:0 auto; }

        .feedback-showcase {
            opacity:0; transform:translateY(24px);
            transition:opacity .6s ease-out, transform .6s ease-out;
        }
        .feedback-showcase.is-visible { opacity:1; transform:translateY(0); }
        @media(prefers-reduced-motion:reduce){ .feedback-showcase { opacity:1; transform:none; transition:none; } }

        .feedback-card {
            background:linear-gradient(135deg,rgba(30,41,59,.95),rgba(15,23,42,.95));
            border:1px solid rgba(168,85,247,.3);
            border-radius:20px; overflow:hidden;
            box-shadow:0 20px 60px rgba(0,0,0,.4), 0 0 0 1px rgba(168,85,247,.1);
        }
        .feedback-card-header {
            display:flex; align-items:center; gap:1rem;
            padding:1.25rem 1.5rem; flex-wrap:wrap;
            background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(168,85,247,.1));
            border-bottom:1px solid rgba(168,85,247,.15);
        }
        .feedback-avatar {
            width:42px; height:42px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg,var(--indigo),var(--purple));
            display:flex; align-items:center; justify-content:center;
            font-size:.85rem; font-weight:800; color:white;
        }
        .feedback-name { font-size:.975rem; font-weight:700; color:white; }
        .feedback-event { font-size:.78rem; color:var(--gray); margin-top:.15rem; }
        .placement-badge {
            margin-left:auto;
            background:linear-gradient(135deg,rgba(168,85,247,.2),rgba(236,72,153,.15));
            border:1px solid rgba(168,85,247,.35);
            color:#c084fc; font-size:.78rem; font-weight:700;
            padding:.3rem .85rem; border-radius:9999px; white-space:nowrap;
        }
        .feedback-body { padding:1.5rem; }
        .feedback-ai-label {
            display:inline-flex; align-items:center; gap:.4rem;
            font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
            color:#c084fc; margin-bottom:1rem;
        }
        .feedback-ai-label svg { width:14px; height:14px; }
        .feedback-text { font-size:.925rem; color:var(--gray-lt); line-height:1.75; margin-bottom:1.25rem; }
        .feedback-caption {
            text-align:center; color:var(--gray); font-size:.8rem;
            margin-top:1.25rem; font-style:italic;
        }

        /* ── PARTNER ── */
        .partner {
            padding:6rem 2rem; background:var(--navy);
            position:relative; overflow:hidden;
        }
        .partner::before {
            content:''; position:absolute; top:-40%; left:-15%;
            width:600px; height:600px;
            background:radial-gradient(circle,rgba(99,102,241,.12),transparent 70%); pointer-events:none;
        }
        .partner::after {
            content:''; position:absolute; bottom:-30%; right:-10%;
            width:500px; height:500px;
            background:radial-gradient(circle,rgba(168,85,247,.1),transparent 70%); pointer-events:none;
        }
        .partner-inner { position:relative; max-width:900px; margin:0 auto; text-align:center; }
        .partner-box {
            background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(168,85,247,.1));
            border:1px solid rgba(99,102,241,.22); border-radius:24px; padding:4rem 3rem;
        }
        .benefits { display:flex; flex-direction:column; gap:.85rem; margin:2rem auto; max-width:500px; text-align:left; }
        .benefit { display:flex; align-items:flex-start; gap:.75rem; color:var(--gray-lt); font-size:.93rem; }
        .check {
            width:22px; height:22px; border-radius:50%;
            background:linear-gradient(135deg,var(--indigo),var(--purple));
            display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px;
        }
        .check svg { width:11px; height:11px; }

        /* ── FOOTER ── */
        footer { background:rgba(0,0,0,.45); border-top:1px solid rgba(99,102,241,.1); padding:2rem; }
        .foot-inner {
            max-width:1200px; margin:0 auto;
            display:flex; align-items:center; justify-content:space-between;
            flex-wrap:wrap; gap:1rem;
        }
        .foot-copy { color:var(--gray); font-size:.85rem; }
        .foot-links { display:flex; gap:1.5rem; }
        .foot-links a { color:var(--gray); font-size:.85rem; text-decoration:none; transition:color .2s; }
        .foot-links a:hover { color:var(--indigo-lt); }

        @media(max-width:640px){
            nav { padding:.875rem 1.25rem; }
            .hero { padding:4rem 1.25rem 3.5rem; }
            .features,.partner { padding:4rem 1.25rem; }
            .partner-box { padding:2.5rem 1.5rem; }
            .foot-inner { flex-direction:column; text-align:center; }
            .foot-links { justify-content:center; }
        }

        /* ── Scroll-triggered card reveal ── */
        .card {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.52s cubic-bezier(0.22,1,0.36,1),
                        transform 0.52s cubic-bezier(0.22,1,0.36,1);
        }
        .card.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        /* section head reveal */
        .section-head {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
        }
        .section-head.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        /* partner box reveal */
        .partner-box {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity 0.55s ease-out, transform 0.55s ease-out;
        }
        .partner-box.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        /* Respect prefers-reduced-motion */
        @media (prefers-reduced-motion: reduce) {
            .card, .section-head, .partner-box {
                opacity: 1; transform: none; transition: none;
            }
        }
    </style>
</head>
<body>

    <!-- ═══ NAV ═══ -->
    <nav aria-label="Main navigation" x-data="{ mobileOpen: false }">
        <div class="nav-inner">
            <a href="/" class="nav-logo" aria-label="Kompetic home">Kompetic</a>
            <div class="nav-right">
                <div class="nav-links" aria-label="Site sections">
                    <a href="#features">Features</a>
                    <a href="#ai-feedback">For Competitors</a>
                    <a href="#partner">Partner Program</a>
                </div>
                <span class="badge-beta" aria-label="Currently in beta">Beta</span>
                <a href="{{ route('filament.portal.auth.login') }}" class="btn-nav">Log In</a>
                <button class="nav-hamburger" x-on:click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen" aria-label="Toggle navigation">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="mobile-menu" :class="mobileOpen ? 'open' : ''" aria-hidden="!mobileOpen">
            <a href="#features" x-on:click="mobileOpen = false">Features</a>
            <a href="#ai-feedback" x-on:click="mobileOpen = false">For Competitors</a>
            <a href="#partner" x-on:click="mobileOpen = false">Partner Program</a>
            <a href="{{ route('filament.portal.auth.login') }}">Log In</a>
        </div>
    </nav>

    <!-- ═══ HERO ═══ -->
    <section class="hero" aria-labelledby="hero-heading">
        <div class="hero-inner">
            <div class="hero-eyebrow">Now in Beta &mdash; Partner Organisations Welcome</div>

            <h1 id="hero-heading">
                Competition Management,<br>
                <span class="grad">Built for Champions.</span>
            </h1>

            <p class="hero-sub">
                Run tournaments, manage registrations with custom forms, check in competitors with QR codes, score timed bouts with tiebreaks and overtime, and unlock AI-powered insights — all in one modern platform built for organisations that take competition seriously.
            </p>

            <div class="hero-ctas">
                <a href="#partner" class="btn-primary">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                    Become a Partner
                </a>
                <a href="#features" class="btn-secondary">See Features &darr;</a>
            </div>

            <!-- Org Search -->
            <div class="org-search-wrap" x-data="{
                query: '',
                results: null,
                loading: false,
                timer: null,
                async search() {
                    if (this.query.trim().length < 2) { this.results = null; return; }
                    clearTimeout(this.timer);
                    this.timer = setTimeout(async () => {
                        this.loading = true;
                        try {
                            const res = await fetch('{{ route('orgs.search') }}?q=' + encodeURIComponent(this.query));
                            this.results = await res.json();
                        } finally {
                            this.loading = false;
                        }
                    }, 300);
                }
            }">
                <p class="org-search-label">Already have an account? Find your organisation:</p>
                <div class="search-row" role="search">
                    <input
                        type="text"
                        x-model="query"
                        x-on:input="search()"
                        placeholder="Search organisations..."
                        aria-label="Search organisations"
                        inputmode="search"
                        autocomplete="off"
                    >
                    <button type="button" x-on:click="search()" :disabled="loading" aria-label="Search">
                        <span x-show="!loading">Search</span>
                        <span x-show="loading" aria-hidden="true">…</span>
                    </button>
                </div>

                <div x-show="results !== null" class="org-results" aria-live="polite">
                    <template x-if="results && results.length > 0">
                        <div style="display:flex;flex-direction:column;gap:.5rem;">
                            <template x-for="org in results" :key="org.slug">
                                <a :href="org.url" class="org-card">
                                    <div>
                                        <div class="org-name" x-text="org.name"></div>
                                        <div class="org-url" x-text="org.slug + '.kompetic.com'"></div>
                                    </div>
                                    <svg class="org-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="results && results.length === 0">
                        <p class="no-results">No organisations found for "<span x-text="query"></span>". Check the name with your competition organiser.</p>
                    </template>
                </div>
            </div>

        </div>
    </section>

    <!-- ═══ FEATURES ═══ -->
    <section class="features" id="features" aria-labelledby="features-heading">
        <div class="section-head">
            <span class="label">Platform Features</span>
            <h2 id="features-heading">Everything you need to run a world-class event</h2>
            <p class="sub">From the first registration to the final podium, Kompetic covers every step of the competition lifecycle — with AI working behind the scenes to help you improve.</p>
        </div>

        <div class="grid-features">

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </div>
                <h3>Tournament Management</h3>
                <p>Create events, configure divisions, build draws and manage the full competition lifecycle from a single intuitive panel.</p>
                <div class="card-example">
                    <div class="ex-label">Your competitions</div>
                    <div class="ex-row"><span class="ex-val">Spring Championship 2025</span><span class="ex-badge ex-blue" style="margin-left:auto">▶ Running</span></div>
                    <div class="ex-row"><span class="ex-val">Winter Invitational</span><span class="ex-badge ex-green" style="margin-left:auto">Open</span></div>
                    <div class="ex-row"><span class="ex-val">Autumn Regional</span><span class="ex-badge ex-gray" style="margin-left:auto">Planning</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <h3>Registrations</h3>
                <p>Streamline competitor sign-ups with custom registration forms, automatic confirmation notifications, and capacity limits per division.</p>
                <div class="card-example">
                    <div style="display:flex;gap:0;margin-bottom:.55rem;">
                        <span class="ex-badge ex-green" style="border-radius:9999px 0 0 9999px;padding:.2rem .6rem;">✓ Details</span>
                        <span class="ex-badge ex-indigo" style="border-radius:0 9999px 9999px 0;padding:.2rem .6rem;border-left:none;">2 Choose Events</span>
                    </div>
                    <div class="ex-row"><span>Kata</span><span class="ex-dim">— Intermediate Female U18</span><span class="ex-badge ex-green" style="margin-left:auto">✓</span></div>
                    <div class="ex-row"><span>Open Kumite</span><span class="ex-dim">— Adult Male</span><span class="ex-badge ex-green" style="margin-left:auto">✓</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 14h3v3m0 4h4m-7 0v-4m4-3v3"/></svg>
                </div>
                <h3>QR Check-In &amp; Weigh-Ins</h3>
                <p>Scan competitors in at the door using unique QR codes and record weigh-in results on the spot. Fast, contactless, and eliminates manual paper lists on event day.</p>
                <div class="card-example">
                    <div class="ex-row"><span class="ex-badge ex-green">✓ Checked in</span><span class="ex-val">Alex Kim</span><span class="ex-dim" style="margin-left:auto">9:42 am</span></div>
                    <div class="ex-row"><span class="ex-dim">Events:</span><span>Kata &middot; Open Kumite</span></div>
                    <div class="ex-row"><span class="ex-dim">Weigh-in:</span><span class="ex-val">61.2 kg</span><span class="ex-badge ex-green" style="margin-left:auto">✓ Recorded</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
                <h3>Live Scoring</h3>
                <p>Score timed bouts in real time with configurable round timers, tiebreak rounds, and overtime support. Judges use quick-tap score events; spectators and athletes see results the moment they're posted.</p>
                <div class="card-example">
                    <div class="ex-prog"><div class="ex-bar"><div class="ex-bar-fill" style="width:63%"></div></div><span>14 / 22 complete</span></div>
                    <div class="ex-row" style="background:rgba(34,197,94,.08);border-radius:6px;padding:.3rem .5rem;margin-bottom:.2rem;border-bottom:none;"><span class="ex-mono ex-val">K-INT-F</span><span>Kata — Mat A</span><span class="ex-badge ex-green" style="margin-left:auto">Complete</span></div>
                    <div class="ex-row" style="background:rgba(251,191,36,.08);border-radius:6px;padding:.3rem .5rem;margin-bottom:.2rem;border-bottom:none;"><span class="ex-mono" style="color:#fde68a;font-weight:700">K-BEG-M</span><span>Kata — Mat B</span><span class="ex-badge ex-amber" style="margin-left:auto">In progress</span></div>
                    <div class="ex-row" style="padding:.3rem .5rem;"><span class="ex-mono ex-dim">K-ADV-F</span><span class="ex-dim">Kata — Mat A</span><span class="ex-dim" style="margin-left:auto">Waiting</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <h3>Competitor Portal</h3>
                <p>Athletes manage their profiles, view event schedules, track registrations, manage dojo affiliations, and access results through a clean self-service portal.</p>
                <div class="card-example">
                    <div class="ex-row" style="align-items:flex-start;gap:.6rem">
                        <div style="width:30px;height:30px;border-radius:6px;background:linear-gradient(135deg,var(--indigo),var(--purple));display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;font-size:.5rem;font-weight:800;color:white;line-height:1.2;letter-spacing:.03em">
                            <span>JUN</span><span style="font-size:.85rem;line-height:1">8</span>
                        </div>
                        <div style="flex:1;min-width:0">
                            <div style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap"><span class="ex-val">Spring Championship 2025</span><span class="ex-badge ex-green">Open</span></div>
                            <div class="ex-dim">Check-in 8:30 am &middot; City Sports Centre</div>
                        </div>
                    </div>
                    <div class="ex-row"><span class="ex-dim">Jordan Lee</span><span class="ex-badge ex-indigo" style="margin-left:auto">Register now →</span></div>
                    <div class="ex-row"><span>&#x1F948;</span><span>2nd &mdash; Kata (Intermediate Female U18)</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <h3>Notifications &amp; Messaging</h3>
                <p>Keep everyone in the loop with automatic confirmations, schedule updates, and draw announcements. Send targeted competition messages to competitors for last-minute changes — no manual emailing required.</p>
                <div class="card-example">
                    <div class="ex-row" style="align-items:flex-start"><span style="font-size:.9rem;flex-shrink:0">📧</span><div><div class="ex-val">Registration confirmed</div><div class="ex-dim">Spring Championship — 2 athletes registered</div></div></div>
                    <div class="ex-row" style="align-items:flex-start"><span style="font-size:.9rem;flex-shrink:0">📢</span><div><div class="ex-val">Message from organiser</div><div class="ex-dim">Weigh-ins begin at 7:45 am. Please arrive early.</div></div></div>
                    <div class="ex-row" style="align-items:flex-start"><span style="font-size:.9rem;flex-shrink:0">📧</span><div><div class="ex-val">Your results are in</div><div class="ex-dim">Personal feedback + placements now available in your portal</div></div></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3>Event Organisation</h3>
                <p>A full back-office for event organisers — manage members, clubs, officials and events across multiple locations from one place.</p>
                <div class="card-example">
                    <div class="ex-row"><span class="ex-dim">Members</span><span class="ex-val">94</span><span class="ex-dim" style="margin-left:auto">Clubs</span><span class="ex-val">6</span></div>
                    <div class="ex-row"><span class="ex-dim">Officials</span><span class="ex-val">8</span><span class="ex-dim" style="margin-left:auto">Locations</span><span class="ex-val">3</span></div>
                    <div class="ex-row"><span class="ex-dim">Competitions run</span><span class="ex-val">17 all-time</span><span class="ex-dim" style="margin-left:auto">Upcoming</span><span class="ex-val">2</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                </div>
                <h3>Flexible Event Configuration</h3>
                <p>Configure every event exactly how your sport demands — set weight categories, age groups, scoring formats, and tiebreak rules. No two disciplines are the same, and Kompetic doesn't pretend they are.</p>
                <div class="card-example">
                    <div class="ex-label">Kumite — event config</div>
                    <div class="ex-row"><span class="ex-dim">Format</span><span>Round-robin → Final</span></div>
                    <div class="ex-row"><span class="ex-dim">Scoring</span><span>Points (time-based bouts)</span></div>
                    <div class="ex-row"><span class="ex-dim">Age bands</span><span>U14 &middot; U18 &middot; Adult &middot; Masters</span></div>
                    <div class="ex-row"><span class="ex-dim">Weight classes</span><span>-60 kg &middot; -70 kg &middot; +70 kg</span></div>
                    <div class="ex-row"><span class="ex-dim">Max / division</span><span class="ex-val">8</span><span class="ex-dim" style="margin-left:auto">Tiebreak</span><span>Sudden death</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </div>
                <h3>Competition Templates</h3>
                <p>Save your event configuration as a reusable template. Spin up repeat tournaments — same divisions, rules, and scoring settings — in minutes instead of hours.</p>
                <div class="card-example">
                    <div class="ex-label">Saved templates</div>
                    <div class="ex-row" style="align-items:flex-start">
                        <div style="flex:1"><div class="ex-val">📋 Autumn Regional</div><div class="ex-dim">Kata &middot; Kumite &middot; Weapons &middot; 5 age bands &middot; Mat A / B / C</div></div>
                        <span class="ex-badge ex-indigo" style="flex-shrink:0">Use →</span>
                    </div>
                    <div class="ex-row"><span class="ex-dim">Last used for</span><span>Winter Invitational &mdash; set up in 4 min</span></div>
                </div>
            </article>

            <article class="card">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#818cf8" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l3-3 2 2 4-4 3 3 3-3"/></svg>
                </div>
                <h3>Results &amp; Placements</h3>
                <p>Rankings are calculated automatically from live scores and published instantly. Manual placement overrides handle edge cases without disrupting the rest of the draw.</p>
                <div class="card-example">
                    <div class="ex-label">Kata — Intermediate Female U18</div>
                    <div class="ex-row"><span>🥇</span><span class="ex-val">Kim, Sarah</span><span class="ex-dim" style="margin-left:auto">47.5 pts</span></div>
                    <div class="ex-row"><span>🥈</span><span class="ex-val">Tanaka, Yuki</span><span class="ex-dim" style="margin-left:auto">45.0 pts</span></div>
                    <div class="ex-row"><span>🥉</span><span class="ex-val">Santos, Rosa</span><span class="ex-dim" style="margin-left:auto">43.5 pts</span></div>
                    <div class="ex-row"><span class="ex-dim">4.</span><span class="ex-dim">Park, Ji-won</span><span class="ex-dim" style="margin-left:auto">42.0 pts</span></div>
                </div>
            </article>

            <article class="card ai">
                <div class="icon-wrap" aria-hidden="true">
                    <svg fill="none" viewBox="0 0 24 24" stroke="#c084fc" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                </div>
                <h3>AI Insights <span class="ai-pill">AI</span></h3>
                <p>Kompetic analyses your event data to surface performance trends, participation patterns, and actionable recommendations — helping you run better competitions every time.</p>
                <div class="card-example" style="border-color:rgba(168,85,247,.15)">
                    <div style="color:#86efac;font-size:.68rem;font-weight:700;margin-bottom:.3rem">✅ Action Items</div>
                    <div class="ex-dim" style="padding-left:.5rem;margin-bottom:.45rem">&middot; 3 solo divisions — combine Kata Beginner U12 + U14</div>
                    <div style="color:#c084fc;font-size:.68rem;font-weight:700;margin-bottom:.3rem">🌟 What's Going Well</div>
                    <div class="ex-dim" style="padding-left:.5rem;margin-bottom:.45rem">&middot; 18 new registrations in last 7 days — strong momentum</div>
                    <div style="color:#93c5fd;font-size:.68rem;font-weight:700;margin-bottom:.3rem">💰 Financial Summary</div>
                    <div class="ex-dim" style="padding-left:.5rem">&middot; $1,240 received &middot; $320 outstanding (4 registrations)</div>
                </div>
            </article>

        </div>
    </section>

    <!-- ═══ AI FEEDBACK ═══ -->
    <section class="ai-feedback-section" id="ai-feedback" aria-labelledby="ai-feedback-heading">
        <div class="ai-feedback-inner">
            <div class="section-head" style="margin-bottom:3rem;">
                <span class="label">For Competitors</span>
                <h2 id="ai-feedback-heading">Every athlete leaves with<br><span class="grad">personalised AI coaching</span></h2>
                <p class="sub">After each competition, Kompetic automatically generates personalised feedback and encouragement for every competitor — celebrating wins, recognising growth, and pointing to what to work on next.</p>
            </div>

            <div class="feedback-showcase">
                <div class="feedback-card">
                    <div class="feedback-card-header">
                        <div class="feedback-avatar" aria-hidden="true">AK</div>
                        <div>
                            <div class="feedback-name">Alex Kim</div>
                            <div class="feedback-event">3 events &mdash; Spring Championship 2025</div>
                        </div>
                        <div class="placement-badge">&#x1F948; 2nd Place</div>
                    </div>
                    <div class="feedback-body">
                        <div class="feedback-ai-label" aria-label="AI-generated feedback">
                            <svg fill="none" viewBox="0 0 24 24" stroke="#c084fc" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            AI Feedback
                        </div>
                        <p class="feedback-text">
                            Alex had a strong day, taking 2nd in Kata and adding a 3rd in the Open Kumite division — two podium finishes across very different disciplines in a single competition. They also competed in Team Kata, contributing confidently to the group's performance throughout. Results like these show real range and consistency, and both placings reflect genuine skill under pressure. Keep building on it — the top step is well within reach.
                        </p>
                    </div>
                </div>
                <p class="feedback-caption">Example AI feedback — generated automatically and delivered to each competitor after results are finalised.</p>
            </div>
        </div>
    </section>

    <!-- ═══ PARTNER ═══ -->
    <section class="partner" id="partner" aria-labelledby="partner-heading">
        <div class="partner-inner">
            <div class="partner-box">
                <span class="label">Beta Partner Program</span>
                <h2 id="partner-heading">Help Shape the Future of<br>Competition Management</h2>
                <p class="sub" style="max-width:580px;margin:1rem auto 0;">
                    Kompetic is still in beta and we're actively looking for forward-thinking organisations to partner with us. Your real-world feedback will directly shape the platform — and early partners earn a special reward when we fully launch.
                </p>

                <div class="benefits" role="list">
                    <div class="benefit" role="listitem">
                        <div class="check" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong style="color:white;">Free lifetime access</strong> when Kompetic fully launches — no subscription fees, no catch.</span>
                    </div>
                    <div class="benefit" role="listitem">
                        <div class="check" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong style="color:white;">Influence the roadmap</strong> — tell us what matters most and see it built into the platform.</span>
                    </div>
                    <div class="benefit" role="listitem">
                        <div class="check" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong style="color:white;">Priority onboarding</strong> with dedicated support from the Kompetic team from day one.</span>
                    </div>
                    <div class="benefit" role="listitem">
                        <div class="check" aria-hidden="true"><svg fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                        <span><strong style="color:white;">Run real events</strong> on the platform ahead of the public launch — be first to the podium.</span>
                    </div>
                </div>

                <p style="color:var(--gray);margin-bottom:1.75rem;font-size:.95rem;">
                    Ready to partner? We'd love to hear from you:
                </p>
                <a href="mailto:support@kompetic.com" class="btn-primary" style="display:inline-flex;">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" aria-hidden="true"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    support@kompetic.com
                </a>
            </div>
        </div>
    </section>

    <!-- ═══ FOOTER ═══ -->
    <footer>
        <div class="foot-inner">
            <span class="foot-copy">&copy; {{ date('Y') }} Kompetic. All rights reserved.</span>
            <nav class="foot-links" aria-label="Footer navigation">
                <a href="mailto:support@kompetic.com">support@kompetic.com</a>
                <a href="#features">Features</a>
                <a href="#partner">Partner Program</a>
                <a href="{{ route('filament.portal.auth.login') }}">Log In</a>
            </nav>
        </div>
    </footer>

    <script>
        (function () {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const STAGGER = 90;

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;
                    const el = entry.target;
                    const delay = parseInt(el.dataset.revealDelay || '0', 10);
                    setTimeout(() => el.classList.add('is-visible'), delay);
                    observer.unobserve(el);
                });
            }, { threshold: 0.12 });

            // Feature cards — stagger by actual rendered column count
            const grid = document.querySelector('.grid-features');
            const cards = document.querySelectorAll('.card');
            if (grid && cards.length) {
                const colCount = () => {
                    const firstCard = cards[0];
                    if (!firstCard) return 1;
                    const gridLeft = grid.getBoundingClientRect().left;
                    let cols = 1;
                    let prevLeft = firstCard.getBoundingClientRect().left;
                    for (let i = 1; i < cards.length; i++) {
                        const left = cards[i].getBoundingClientRect().left;
                        if (left > prevLeft) cols++;
                        else break;
                        prevLeft = left;
                    }
                    return cols;
                };
                const cols = colCount();
                cards.forEach((card, i) => {
                    card.dataset.revealDelay = cols > 1 ? (i % cols) * STAGGER : 0;
                    observer.observe(card);
                });
            }

            document.querySelectorAll('.section-head, .partner-box, .feedback-showcase').forEach(el => {
                observer.observe(el);
            });
        })();
    </script>
</body>
</html>
