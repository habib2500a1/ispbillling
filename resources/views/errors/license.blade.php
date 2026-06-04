<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>License required</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; padding: 1.5rem; }
        .box { max-width: 28rem; background: #1e293b; border: 1px solid #334155; border-radius: 1rem; padding: 1.5rem; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { margin: 0.5rem 0; font-size: 0.9rem; color: #94a3b8; line-height: 1.5; }
        code { font-size: 0.8rem; background: #0f172a; padding: 0.15rem 0.35rem; border-radius: 0.25rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Platform license</h1>
        <p>{{ $message ?? 'This installation requires a valid license.' }}</p>
        <p>Deployment: <code>{{ $deployment ?? 'on_premise' }}</code></p>
        <p>Set <code>ISP_LICENSE_KEY</code> in <code>.env</code> or contact your vendor.</p>
    </div>
</body>
</html>
