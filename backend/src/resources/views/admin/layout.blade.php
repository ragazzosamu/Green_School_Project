<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — GreenSchool</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #F7F6F2;
            --surface:   #FFFFFF;
            --surface2:  #F2F1ED;
            --border:    #E4E2DA;
            --text:      #1A1916;
            --text-2:    #6B6860;
            --text-3:    #A8A69E;
            --accent:    #2A6B4A;
            --accent-bg: #EBF5EF;
            --purple:    #7C3AED;
            --purple-bg: #F5F3FF;
            --purple-border: #DDD6FE;
            --red:       #DC2626;
            --red-bg:    #FEF2F2;
            --gold:      #D4A017;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 16px rgba(0,0,0,0.08);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed; inset: 0;
            background-image: radial-gradient(circle, #C8C6BE 1px, transparent 1px);
            background-size: 28px 28px;
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
        }

        /* ── SIDEBAR ── */
        .admin-sidebar {
            width: 240px;
            flex-shrink: 0;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            box-shadow: var(--shadow-md);
        }

        .sidebar-logo {
            padding: 1.5rem 1.25rem 1rem;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo-top {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 4px;
        }

        .sidebar-logo-mark {
            width: 28px; height: 28px;
            background: var(--purple);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(124,58,237,0.25);
        }

        .sidebar-logo-text {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.1rem;
            color: var(--text);
        }

        .sidebar-badge {
            display: inline-flex;
            align-items: center;
            background: var(--purple-bg);
            border: 1px solid var(--purple-border);
            border-radius: 100px;
            padding: 2px 10px;
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--purple);
            margin-top: 4px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow-y: auto;
        }

        .sidebar-section {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-3);
            padding: 0.75rem 0.5rem 0.35rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-2);
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }

        .sidebar-link:hover { background: var(--surface2); color: var(--text); }

        .sidebar-link.active {
            background: var(--purple-bg);
            color: var(--purple);
            font-weight: 600;
        }

        .sidebar-link-icon { font-size: 1rem; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 1rem 0.75rem;
            border-top: 1px solid var(--border);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            background: var(--surface2);
            margin-bottom: 8px;
        }

        .sidebar-user-avatar {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: var(--purple);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }

        .sidebar-user-name { font-size: 0.78rem; font-weight: 600; color: var(--text); }
        .sidebar-user-role { font-size: 0.65rem; color: var(--purple); font-weight: 500; }

        .sidebar-back-btn {
            display: flex; align-items: center; gap: 8px;
            width: 100%;
            padding: 8px 12px;
            background: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--text-2);
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s;
            font-family: inherit;
            margin-bottom: 6px;
        }
        .sidebar-back-btn:hover { background: var(--surface2); color: var(--text); }

        .sidebar-logout {
            width: 100%;
            padding: 8px 12px;
            background: none;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--red);
            cursor: pointer;
            font-family: inherit;
            text-align: left;
            transition: all 0.15s;
        }
        .sidebar-logout:hover { background: var(--red-bg); }

        /* ── MAIN ── */
        .admin-main {
            margin-left: 240px;
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .admin-topbar {
            background: rgba(247,246,242,0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            padding: 0 2rem;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .admin-topbar-title {
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.1rem;
            color: var(--text);
        }

        .admin-content {
            padding: 2rem;
            max-width: 1200px;
        }

        /* ── COMPONENTI CONDIVISI ── */
        .admin-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .admin-card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .admin-card-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-3);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .admin-table th {
            text-align: left;
            padding: 0.7rem 1.25rem;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-3);
            border-bottom: 1px solid var(--border);
            background: var(--surface2);
        }

        .admin-table td {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-2);
            vertical-align: middle;
        }

        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: var(--surface2); }

        .badge-pill {
            display: inline-flex; align-items: center;
            padding: 2px 10px; border-radius: 100px;
            font-size: 0.65rem; font-weight: 600; letter-spacing: 0.06em;
        }

        .badge-green  { background: #DCFCE7; color: #16A34A; }
        .badge-red    { background: #FEE2E2; color: #DC2626; }
        .badge-yellow { background: #FEF9C3; color: #CA8A04; }
        .badge-purple { background: var(--purple-bg); color: var(--purple); }
        .badge-gray   { background: var(--surface2); color: var(--text-3); }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            font-size: 0.75rem; font-weight: 600; font-family: inherit;
            cursor: pointer; text-decoration: none; border: 1px solid transparent;
            transition: all 0.15s; white-space: nowrap;
        }

        .btn-primary { background: var(--purple); color: #fff; }
        .btn-primary:hover { background: #6D28D9; }
        .btn-outline { background: var(--surface); border-color: var(--border); color: var(--text-2); }
        .btn-outline:hover { background: var(--surface2); }
        .btn-danger  { background: var(--red-bg); border-color: #FECACA; color: var(--red); }
        .btn-danger:hover { background: #FEE2E2; }
        .btn-sm { padding: 4px 10px; font-size: 0.7rem; }

        .alert-success {
            background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px;
            padding: 10px 14px; font-size: 0.8rem; color: #16A34A; margin-bottom: 1.25rem;
        }

        .alert-error {
            background: var(--red-bg); border: 1px solid #FECACA; border-radius: 10px;
            padding: 10px 14px; font-size: 0.8rem; color: var(--red); margin-bottom: 1.25rem;
        }

        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; font-size: 0.72rem; font-weight: 600; color: var(--text-2); margin-bottom: 5px; letter-spacing: 0.04em; }
        .form-input {
            width: 100%; padding: 9px 12px; border: 1px solid var(--border);
            border-radius: 8px; font-size: 0.82rem; font-family: inherit;
            background: var(--surface); color: var(--text); outline: none;
            transition: border-color 0.15s;
        }
        .form-input:focus { border-color: var(--purple); }

        @media (max-width: 768px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; flex-direction: row; }
            .admin-main { margin-left: 0; }
            .admin-content { padding: 1rem; }
        }
    </style>
</head>
<body>

{{-- SIDEBAR --}}
<aside class="admin-sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-top">
            <div class="sidebar-logo-mark">⚙</div>
            <span class="sidebar-logo-text">GreenSchool</span>
        </div>
        <span class="sidebar-badge">Pannello Admin</span>
    </div>

    <nav class="sidebar-nav">
        <span class="sidebar-section">Panoramica</span>
        <a href="/admin"          class="sidebar-link {{ request()->is('admin') ? 'active' : '' }}">
            <span class="sidebar-link-icon">📊</span> Dashboard
        </a>

        <span class="sidebar-section">Gestione</span>
        <a href="/admin/utenti"   class="sidebar-link {{ request()->is('admin/utenti*') ? 'active' : '' }}">
            <span class="sidebar-link-icon">👥</span> Utenti
        </a>
        <a href="/admin/sessioni" class="sidebar-link {{ request()->is('admin/sessioni*') ? 'active' : '' }}">
            <span class="sidebar-link-icon">⚡</span> Sessioni
        </a>
        <a href="/admin/stazioni" class="sidebar-link {{ request()->is('admin/stazioni*') ? 'active' : '' }}">
            <span class="sidebar-link-icon">🗺</span> Stazioni
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">👤</div>
            <div>
                <p class="sidebar-user-name">{{ Auth::user()->nome }} {{ Auth::user()->cognome }}</p>
                <p class="sidebar-user-role">Amministratore</p>
            </div>
        </div>
        <a href="/map" class="sidebar-back-btn">← Torna al sito</a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="sidebar-logout">Esci</button>
        </form>
    </div>
</aside>

{{-- MAIN --}}
<div class="admin-main">
    <div class="admin-topbar">
        <span class="admin-topbar-title">@yield('page-title', 'Dashboard')</span>
        <span style="font-size:0.75rem;color:var(--text-3);">{{ now()->format('d M Y') }}</span>
    </div>

    <div class="admin-content">
        @if(session('success'))
            <div class="alert-success">✓ {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert-error">✗ {{ session('error') }}</div>
        @endif

        @yield('content')
    </div>
</div>

</body>
</html>