<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified — Compete</title>
    @vite(['resources/css/app.css'])
    <style>
        body { background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: ui-sans-serif, system-ui, sans-serif; }
        .card { background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 2.5rem; max-width: 24rem; width: 100%; text-align: center; }
        .icon { width: 3rem; height: 3rem; background: #d1fae5; border-radius: 9999px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; }
        .icon svg { width: 1.5rem; height: 1.5rem; color: #059669; }
        h1 { font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem; }
        p { font-size: 0.875rem; color: #6b7280; line-height: 1.6; margin-bottom: 1.5rem; }
        a { display: block; background: #1e3a6e; color: #fff; padding: 0.625rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none; }
        a:hover { background: #162d55; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>
        <h1>Email address verified</h1>
        <p>Thanks for confirming your email address. Your account is now awaiting admin approval — you will receive an email once access has been granted.</p>
        <a href="{{ route('filament.portal.auth.login') }}">Back to login</a>
    </div>
</body>
</html>
