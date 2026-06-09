<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Please try again</title>
    <meta http-equiv="refresh" content="6;url={{ url('/login') }}">
    <style>
        :root {
            color-scheme: dark;
            --bg: #09090b;
            --surface: #18181b;
            --border: rgba(255,255,255,.1);
            --text: #fafafa;
            --muted: #a1a1aa;
            --accent: #818cf8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            font-family: Outfit, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .box {
            max-width: 24rem;
            width: 100%;
            padding: 2rem 1.5rem;
            text-align: center;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 1.125rem;
            box-shadow: 0 12px 40px rgba(0,0,0,.45);
        }
        .icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(248,113,113,.12);
            color: #f87171;
            font-size: 1.35rem;
            font-weight: 700;
        }
        h1 { margin: 0 0 .5rem; font-size: 1.15rem; font-weight: 800; }
        p { margin: .35rem 0; font-size: .9375rem; color: var(--muted); line-height: 1.55; }
        .links { margin-top: 1.25rem; font-size: .875rem; }
        a { color: var(--accent); text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon" aria-hidden="true">!</div>
        <h1>Could not sign you in</h1>
        <p>We hit a temporary error. You will be redirected to the login page shortly.</p>
        <p class="links">
            <a href="{{ url('/login') }}">Back to sign in</a>
            ·
            <a href="{{ url('/') }}">Home</a>
        </p>
    </div>
</body>
</html>
