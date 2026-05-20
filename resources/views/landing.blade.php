<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kompetic — Competition Management</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; margin: 0; min-height: 100vh; }
        .hero { background: #1e3a6e; color: white; padding: 3rem 1.5rem; text-align: center; }
        .hero h1 { font-size: 2.5rem; font-weight: 700; margin: 0 0 0.5rem; }
        .hero p  { font-size: 1.125rem; opacity: 0.8; margin: 0 0 2rem; }
        .search-box { max-width: 480px; margin: 0 auto; display: flex; gap: 0.5rem; }
        .search-box input  { flex: 1; padding: 0.75rem 1rem; border-radius: 0.5rem; border: none; font-size: 1rem; outline: none; }
        .search-box button { padding: 0.75rem 1.5rem; background: #e07828; color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .search-box button:hover { background: #c9671f; }
        .results { max-width: 600px; margin: 2rem auto; padding: 0 1.5rem; }
        .org-card { background: white; border-radius: 0.75rem; padding: 1.25rem 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,0.08); text-decoration: none; color: inherit; border: 1px solid #e5e7eb; transition: box-shadow 0.15s; }
        .org-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
        .org-name { font-size: 1.125rem; font-weight: 600; color: #111827; }
        .org-url  { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }
        .org-arrow { color: #e07828; }
        .no-results { text-align: center; color: #6b7280; padding: 2rem 0; }
        .admin-link { text-align: center; margin-top: 3rem; padding-bottom: 3rem; }
        .admin-link a { color: #6b7280; font-size: 0.875rem; text-decoration: none; }
        .admin-link a:hover { color: #374151; }
    </style>
</head>
<body>
    <div class="hero">
        <h1>Kompetic</h1>
        <p>Find your organisation's competition portal</p>
        <form method="GET" action="/" class="search-box">
            <input type="text" name="q" placeholder="Search organisations..." value="{{ $query ?? '' }}" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>

    <div class="results">
        @if(isset($query) && $query !== '')
            @if($orgs->isNotEmpty())
                @foreach($orgs as $org)
                    <a href="{{ config('app.scheme') . '://' . $org->slug . '.' . config('app.domain', 'kompetic.com') . '/portal' }}" class="org-card">
                        <div>
                            <div class="org-name">{{ $org->name }}</div>
                            <div class="org-url">{{ $org->slug }}.kompetic.com</div>
                        </div>
                        <svg class="org-arrow" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            @else
                <div class="no-results">
                    <p>No organisations found for "<strong>{{ $query }}</strong>"</p>
                    <p>Check the name with your competition organiser.</p>
                </div>
            @endif
        @endif
    </div>

    <div class="admin-link">
        <a href="/admin">System administration</a>
    </div>
</body>
</html>
