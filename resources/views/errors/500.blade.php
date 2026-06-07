<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong</title>
    <meta http-equiv="refresh" content="8">
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; padding: 1.5rem; }
        .box { max-width: 28rem; background: #1e293b; border: 1px solid #334155; border-radius: 1rem; padding: 1.5rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { margin: 0.5rem 0; font-size: 0.95rem; color: #94a3b8; line-height: 1.55; }
        a { color: #a5b4fc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Something went wrong</h1>
        <p>We hit a temporary error. The page will retry automatically, or you can go back to the home page.</p>
        <p><a href="{{ url('/') }}">Home</a> · <a href="{{ url('/admin/login') }}">Admin login</a></p>
    </div>
</body>
</html>
