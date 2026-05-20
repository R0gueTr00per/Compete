<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation — Kompetic</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: system-ui, sans-serif; background: #f1f5f9; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { background: white; border-radius: 0.75rem; padding: 2rem; max-width: 420px; width: calc(100% - 2rem); box-shadow: 0 4px 24px rgba(0,0,0,0.10); border: 1px solid #e5e7eb; }
        .logo { text-align: center; margin-bottom: 1.5rem; }
        .logo span { font-size: 1.5rem; font-weight: 700; color: #1e3a6e; }
        h1 { font-size: 1.25rem; font-weight: 700; color: #111827; margin: 0 0 0.25rem; text-align: center; }
        .subtitle { text-align: center; color: #6b7280; font-size: 0.875rem; margin: 0 0 1.75rem; }
        .org-badge { display: inline-block; background: #eff6ff; color: #1e3a6e; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; margin: 0 auto 1.5rem; display: block; text-align: center; }
        label { display: block; font-size: 0.875rem; font-weight: 500; color: #374151; margin-bottom: 0.375rem; }
        input[type=password] { width: 100%; box-sizing: border-box; padding: 0.625rem 0.875rem; border: 1px solid #d1d5db; border-radius: 0.5rem; font-size: 0.9375rem; outline: none; margin-bottom: 1rem; }
        input[type=password]:focus { border-color: #1e3a6e; box-shadow: 0 0 0 2px rgba(30,58,110,0.12); }
        .hint { font-size: 0.75rem; color: #9ca3af; margin-top: -0.75rem; margin-bottom: 1rem; }
        button[type=submit] { width: 100%; padding: 0.75rem; background: #e07828; color: white; border: none; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button[type=submit]:hover { background: #c9671f; }
        .error { color: #dc2626; font-size: 0.8125rem; margin-top: -0.75rem; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo"><span>Kompetic</span></div>
        <h1>You've been invited</h1>
        <p class="subtitle">Set your password to access your organisation</p>
        <div class="org-badge">{{ $org->name }}</div>

        <p style="font-size:0.875rem;color:#374151;margin-bottom:1.25rem;">
            You're being set up as an <strong>organisation administrator</strong>.
            Choose a password to complete your account setup.
        </p>

        @if($errors->any())
            @foreach($errors->all() as $error)
                <div class="error">{{ $error }}</div>
            @endforeach
        @endif

        <form method="POST" action="{{ route('invite.org-admin.complete', $membership) }}">
            @csrf

            <div>
                <label>Email address</label>
                <input type="text" value="{{ $user->email }}" disabled style="width:100%;box-sizing:border-box;padding:0.625rem 0.875rem;border:1px solid #e5e7eb;border-radius:0.5rem;font-size:0.9375rem;color:#6b7280;background:#f9fafb;margin-bottom:1rem;">
            </div>

            <div>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8">
                <p class="hint">Minimum 8 characters</p>
            </div>

            <div>
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>

            <button type="submit">Set password &amp; get started</button>
        </form>
    </div>
</body>
</html>
