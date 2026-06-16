<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitor access disabled — Kompetic</title>
    @vite(['resources/css/app.css'])
</head>
<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;font-family:'Inter',sans-serif;padding:1.5rem;">
    <div style="max-width:28rem;text-align:center;">
        <h1 style="font-size:1.5rem;font-weight:700;margin-bottom:0.75rem;">Competitor access temporarily disabled</h1>
        <p style="color:#cbd5e1;line-height:1.6;">
            Competitor logins for {{ $currentOrg?->name ?? 'this organisation' }} have been temporarily disabled.
            Please contact Kompetic support for assistance. Organisation administrators can still log in.
        </p>
    </div>
</body>
</html>
