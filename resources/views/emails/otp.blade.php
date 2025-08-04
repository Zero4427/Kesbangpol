<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .otp-code {
            background-color: #f8f9fa;
            border: 2px dashed #007bff;
            padding: 20px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin: 20px 0;
            border-radius: 8px;
            letter-spacing: 5px;
        }
        .info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reset Password OTP</h1>
            <p>Hello {{ $name ?? 'User' }},</p>
            <p>You have requested to reset your password. Please use the OTP code below:</p>
        </div>
        
        <div class="otp-code">
            {{ $otp_code ?? '000000' }}
        </div>
        
        <div class="info">
            <strong>Important:</strong>
            <ul>
                <li>This OTP will expire in {{ $expires_in }} <p>Please use it to reset your password.</p></li>
                <li>Do not share this code with anyone</li>
                <li>If you didn't request this, please ignore this email</li>
            </ul>
        </div>
        
        <div class="footer">
            <p>This is an automated message, please do not reply.</p>
            <p>&copy; {{ date('Y') }} INDOMAS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>