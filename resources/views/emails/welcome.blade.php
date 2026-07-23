<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $companyName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f5;
        }
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #5f61e6 0%, #7c3aed 100%);
            color: white;
            padding: 36px 32px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .header p {
            margin: 0;
            font-size: 15px;
            opacity: 0.88;
        }
        .content {
            padding: 32px;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }
        .body-text {
            font-size: 15px;
            color: #4b5563;
            margin-bottom: 24px;
        }
        .credentials-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .credentials-box .label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .credentials-box .value {
            font-size: 15px;
            color: #111827;
            font-weight: 500;
            margin-bottom: 14px;
        }
        .credentials-box .value:last-child {
            margin-bottom: 0;
        }
        .password-value {
            font-family: 'Courier New', monospace;
            font-size: 16px;
            background: #eef2ff;
            color: #4f46e5;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            letter-spacing: 1px;
        }
        .btn-wrapper {
            text-align: center;
            margin-bottom: 28px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #5f61e6 0%, #7c3aed 100%);
            color: #ffffff !important;
            text-decoration: none;
            padding: 13px 36px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.2px;
        }
        .notice {
            background-color: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 13px;
            color: #92400e;
            margin-bottom: 28px;
        }
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            font-size: 13px;
            color: #9ca3af;
            text-align: center;
        }
        .footer strong {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>Welcome to {{ $companyName }}</h1>
            <p>Your account is ready — let's get started.</p>
        </div>

        <div class="content">
            <p class="greeting">Hi {{ $userName }},</p>
            <p class="body-text">
                Your account has been created on <strong>{{ $companyName }}</strong>'s workspace.
                Here are your login credentials:
            </p>

            <div class="credentials-box">
                <div class="label">Email Address</div>
                <div class="value">{{ $userEmail }}</div>

                @if ($temporaryPassword)
                    <div class="label">Temporary Password</div>
                    <div class="value">
                        <span class="password-value">{{ $temporaryPassword }}</span>
                    </div>
                @endif
            </div>

            @if ($temporaryPassword)
                <div class="notice">
                    <strong>Important:</strong> Please change your password after your first login to keep your account secure.
                </div>
            @endif

            <div class="btn-wrapper">
                <a href="{{ $loginUrl }}" class="btn">Log In to Your Account</a>
            </div>

            <p class="body-text" style="margin-bottom:0;">
                If you have any questions, reach out to your workspace administrator.
            </p>
        </div>

        <div class="footer">
            <p>This email was sent to <strong>{{ $userEmail }}</strong>.<br>
            &copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
