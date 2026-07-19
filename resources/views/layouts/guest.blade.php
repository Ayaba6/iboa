<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'A3-ERP') }} — {{ isset($heading) ? strip_tags((string) $heading) : 'Connexion' }}</title>

    {{-- [Typo globale] Inter en local via @fontsource (bundlée app.css) — CDN supprimé --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ─────────────────────────────────────────────────────────────────
           IMPORTANT : tous les styles ici sont scopés sous `body.guest-page`.
           Cela empêche les règles (fond noir, overflow:hidden…) de "fuiter"
           sur les pages applicatives lors d'une navigation Turbo Drive — où
           le <body> est remplacé mais le <style> du <head> persiste.
        ───────────────────────────────────────────────────────────────── */
        body.guest-page,
        body.guest-page * ,
        body.guest-page *::before,
        body.guest-page *::after { box-sizing: border-box; }

        body.guest-page {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #000;
            font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #fff;
            overflow: hidden;
        }

        /* ── Full-page scene ──────────────────────────────────────────────── */
        body.guest-page .scene {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(180,130,10,.13) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 50% at 50% 100%, rgba(120,80,0,.10) 0%, transparent 70%),
                        #000;
        }

        /* Ambient particles (pure CSS) */
        body.guest-page .scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(212,160,23,.06) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(180,130,10,.05) 0%, transparent 40%);
            pointer-events: none;
        }

        /* ── Logo ─────────────────────────────────────────────────────────── */
        body.guest-page .logo-wrap {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        body.guest-page .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -60%);
            width: 260px;
            height: 100px;
            background: radial-gradient(ellipse at center, rgba(212,160,23,.55) 0%, transparent 70%);
            filter: blur(18px);
            pointer-events: none;
            animation: pulse-glow 3s ease-in-out infinite;
        }

        @keyframes pulse-glow {
            0%, 100% { opacity: .8; transform: translate(-50%, -60%) scale(1); }
            50%       { opacity: 1; transform: translate(-50%, -60%) scale(1.08); }
        }

        body.guest-page .logo-text {
            position: relative;
            font-size: 3rem;
            font-weight: 900;
            letter-spacing: .04em;
            line-height: 1;
            /* Metallic gold gradient */
            background: linear-gradient(
                180deg,
                #FFF0A0 0%,
                #F5C518 18%,
                #D4A017 38%,
                #F5C518 52%,
                #B8860B 68%,
                #F0C040 82%,
                #D4A017 100%
            );
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            /* Gold glow */
            filter: drop-shadow(0 0 16px rgba(212,160,23,.9))
                    drop-shadow(0 0 32px rgba(212,160,23,.5))
                    drop-shadow(0 0 48px rgba(180,130,10,.3));
        }

        /* ── Card ─────────────────────────────────────────────────────────── */
        body.guest-page .card {
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(212,160,23,.35);
            border-radius: 18px;
            padding: 40px 36px;
            position: relative;
            box-shadow:
                0 0 0 1px rgba(212,160,23,.10) inset,
                0 0 40px rgba(212,160,23,.12),
                0 0 80px rgba(180,130,10,.07),
                0 24px 60px rgba(0,0,0,.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Corner accents */
        body.guest-page .card::before,
        body.guest-page .card::after {
            content: '';
            position: absolute;
            width: 28px;
            height: 28px;
            border-color: rgba(212,160,23,.6);
            border-style: solid;
        }
        body.guest-page .card::before {
            top: -1px; left: -1px;
            border-width: 2px 0 0 2px;
            border-radius: 16px 0 0 0;
        }
        body.guest-page .card::after {
            bottom: -1px; right: -1px;
            border-width: 0 2px 2px 0;
            border-radius: 0 0 16px 0;
        }

        /* ── Form elements ────────────────────────────────────────────────── */

        /* Alert banners */
        body.guest-page .g-alert {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: .8125rem;
            margin-bottom: 20px;
        }
        body.guest-page .g-alert-ok  { background: rgba(20,83,45,.4);  border: 1px solid rgba(74,222,128,.25); color: #86efac; }
        body.guest-page .g-alert-err { background: rgba(127,29,29,.4); border: 1px solid rgba(248,113,113,.25); color: #fca5a5; }

        /* Field */
        body.guest-page .g-field {
            position: relative;
            display: flex;
            align-items: center;
            background: rgba(0,0,0,.55);
            border: 1px solid rgba(212,160,23,.28);
            border-radius: 10px;
            transition: border-color .2s, box-shadow .2s;
        }
        body.guest-page .g-field:focus-within {
            border-color: rgba(212,160,23,.7);
            box-shadow: 0 0 0 3px rgba(212,160,23,.12), 0 0 16px rgba(212,160,23,.15);
        }
        body.guest-page .g-field.has-error {
            border-color: rgba(248,113,113,.6);
        }

        body.guest-page .g-icon {
            position: absolute;
            left: 14px;
            color: rgba(212,160,23,.7);
            pointer-events: none;
            flex-shrink: 0;
            display: flex;
        }

        body.guest-page .g-input {
            width: 100%;
            background: transparent;
            border: none;
            outline: none;
            padding: 13px 14px 13px 42px;
            font-size: .9375rem;
            color: rgba(255,255,255,.9);
            font-family: inherit;
        }
        body.guest-page .g-input::placeholder {
            color: rgba(212,160,23,.35);
        }
        body.guest-page .g-input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 100px #0d0d0d inset;
            -webkit-text-fill-color: rgba(255,255,255,.9);
        }

        /* Eye toggle */
        body.guest-page .g-toggle {
            position: absolute;
            right: 13px;
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(212,160,23,.45);
            display: flex;
            align-items: center;
            padding: 4px;
            transition: color .2s;
        }
        body.guest-page .g-toggle:hover { color: rgba(212,160,23,.8); }

        /* Label */
        body.guest-page .g-label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: rgba(212,160,23,.75);
            text-transform: uppercase;
            letter-spacing: .07em;
            margin-bottom: 7px;
        }

        /* Forgot link */
        body.guest-page .g-forgot {
            font-size: .75rem;
            color: rgba(212,160,23,.5);
            text-decoration: none;
            transition: color .2s;
            font-weight: 500;
        }
        body.guest-page .g-forgot:hover { color: rgba(212,160,23,.9); }

        /* Checkbox */
        body.guest-page .g-check-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        body.guest-page .g-check {
            width: 16px; height: 16px;
            accent-color: #D4A017;
            cursor: pointer;
        }
        body.guest-page .g-check-label {
            font-size: .8125rem;
            color: rgba(255,255,255,.45);
            cursor: pointer;
            user-select: none;
        }

        /* Submit button */
        body.guest-page .g-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: .9375rem;
            font-weight: 700;
            letter-spacing: .04em;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #C68B00 0%, #F5C518 40%, #D4A017 60%, #A07010 100%);
            color: #0a0600;
            transition: filter .2s, transform .15s;
            box-shadow: 0 4px 20px rgba(212,160,23,.35), 0 1px 0 rgba(255,255,255,.15) inset;
        }
        body.guest-page .g-btn:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
            box-shadow: 0 6px 28px rgba(212,160,23,.5);
        }
        body.guest-page .g-btn:active { transform: translateY(0); filter: brightness(.95); }

        /* Shine sweep on button */
        body.guest-page .g-btn::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 60%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.25), transparent);
            transition: left .5s;
        }
        body.guest-page .g-btn:hover::after { left: 140%; }

        /* Page footer */
        body.guest-page .g-footer {
            margin-top: 28px;
            text-align: center;
            font-size: .6875rem;
            color: rgba(255,255,255,.22);
            line-height: 1.7;
        }
        body.guest-page .g-footer-brand {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .06em;
            background: linear-gradient(135deg, #F5C518, #D4A017, #F5C518);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 2px;
        }
        body.guest-page .g-footer-sep {
            color: rgba(212,160,23,.35);
            margin: 0 5px;
        }

        /* Fade-in animation */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        body.guest-page .animate-up { animation: fade-up .55s cubic-bezier(.22,.68,0,1.2) both; }
        body.guest-page .animate-up-logo { animation: fade-up .45s cubic-bezier(.22,.68,0,1.2) both; }
        body.guest-page .animate-up-card { animation: fade-up .55s .1s cubic-bezier(.22,.68,0,1.2) both; }
    </style>
</head>
<body class="guest-page">

<div class="scene">

    {{-- ── Logo ─────────────────────────────────────────────────────────── --}}
    <div class="logo-wrap animate-up-logo">
        <div class="logo-glow"></div>
        <div class="logo-text">{{ config('app.name', 'A3-ERP') }}</div>
    </div>

    {{-- ── Card ─────────────────────────────────────────────────────────── --}}
    <div class="card animate-up-card">
        {{ $slot }}

        <div class="g-footer">
            <span class="g-footer-brand">A3-ERP</span>
            <span>
                &copy; {{ date('Y') }} A3 ERP &nbsp;&middot;&nbsp; Tous droits réservés
            </span>
            <br>
            <span>
                <svg style="display:inline;vertical-align:middle;margin-right:3px;opacity:.5;" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>+226 70 03 76 22
            </span>
        </div>
    </div>

</div>

</body>
</html>
