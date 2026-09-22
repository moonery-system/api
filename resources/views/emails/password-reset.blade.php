<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset your Moonery password</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.6;">
    <h1 style="font-size: 20px; font-weight: 600;">Hi {{ $name }}</h1>

    <p>Someone asked to reset the password of your Moonery account. Use the link below
        to choose a new one.</p>

    <p>
        <a href="{{ $url }}" style="display: inline-block; padding: 10px 18px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Choose a new password
        </a>
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        This link expires at {{ $expiresAt->format('d/m/Y H:i') }}.
        If it was not you, ignore this email — your current password stays valid.
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        If the button does not work, copy this address into your browser:<br>
        <span style="word-break: break-all;">{{ $url }}</span>
    </p>
</body>
</html>
