<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Your Verification Code</title>
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

        .otp-box {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            background: #f0f4ff;
            color: #1e3a5f;
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin: 24px 0;
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
        <h2>Identity Verification Code</h2>
        <p>Use the code below to verify your identity before signing. This code expires in <strong>5 minutes</strong>.
        </p>
        <div class="otp-box">{{ $code }}</div>
        <p class="note">If you did not request this code, please ignore this email. Do not share this code with anyone.
        </p>
        <div class="footer">SADC Parliamentary Forum eSign Platform</div>
    </div>
</body>

</html>