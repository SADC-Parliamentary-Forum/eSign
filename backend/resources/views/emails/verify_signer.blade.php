<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Verify Your Email Address</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 24px;
            color: #333;
        }

        .container {
            max-width: 480px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 24px;
        }

        .logo span {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
        }

        h2 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .btn {
            display: inline-block;
            margin: 24px 0;
            background: #1e3a5f;
            color: #fff;
            padding: 14px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
        }

        .url-fallback {
            font-size: 12px;
            color: #888;
            word-break: break-all;
            margin-top: 8px;
        }

        .note {
            font-size: 13px;
            color: #666;
            margin-top: 16px;
        }

        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #aaa;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo"><span>SADC-PF eSign</span></div>
        <h2>Verify Your Email Address</h2>
        <p>Hello {{ $signerName }},</p>
        <p>Please click the button below to verify your email address and complete your identity verification.</p>
        <a href="{{ $verificationUrl }}" class="btn">Verify Email Address</a>
        <p class="url-fallback">Or copy this link into your browser:<br>{{ $verificationUrl }}</p>
        <p class="note">This link will expire in 24 hours. If you did not request this verification, please ignore this
            email.</p>
        <div class="footer">SADC Parliamentary Forum eSign Platform</div>
    </div>
</body>

</html>