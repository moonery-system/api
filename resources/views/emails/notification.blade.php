<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 20px; font-weight: 600;">{{ $title }}</h1>

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    <p style="color: #6b7280; font-size: 14px;">
        You are receiving this email because you have an active Moonery account.
    </p>
</body>
</html>
