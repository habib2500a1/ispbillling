<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0;url={{ $target }}">
    <title>Signing in…</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: system-ui, sans-serif;
            background: #0b1220;
            color: #e2e8f0;
        }
        .box {
            text-align: center;
            padding: 2rem;
        }
        a { color: #a5b4fc; }
    </style>
</head>
<body>
    <div class="box">
        <p>Signing you in…</p>
        <p><a href="{{ $target }}">Continue to admin dashboard</a></p>
    </div>
    <script data-cfasync="false">
        window.setTimeout(function () {
            window.location.replace(@json($target));
        }, 120);
    </script>
</body>
</html>
