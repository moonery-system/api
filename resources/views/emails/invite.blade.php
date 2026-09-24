<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Your Moonery invite</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 20px; font-weight: 600;">Welcome to Moonery, {{ $name }}</h1>

    <p>An account has been created for you. Use the link below to set your password and activate it.</p>

    <p>
        <a href="{{ $url }}" style="display: inline-block; padding: 10px 18px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Set my password
        </a>
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        This link expires at {{ $expiresAt->format('d/m/Y H:i') }}. After that, ask for a new invite.
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        If the button does not work, copy this address into your browser:<br>
        <span style="word-break: break-all;">{{ $url }}</span>
    </p>
</body>
</html>
