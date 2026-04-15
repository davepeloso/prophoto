{{-- Story 3.1 — Expired / revoked share link view --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Expired — {{ $gallery->subject_name }}</title>
</head>
<body>
    <h1>This link has expired</h1>
    <p>The share link for <strong>{{ $gallery->subject_name }}</strong> is no longer active.</p>
    <p>Please contact the studio for a new link.</p>
</body>
</html>
