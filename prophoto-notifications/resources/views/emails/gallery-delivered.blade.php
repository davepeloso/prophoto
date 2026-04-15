<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Gallery is Ready</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .email-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .header {
            margin-bottom: 24px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: #111827;
        }
        .header p {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
        }
        .divider {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 24px 0;
        }
        .message-box {
            background: #f9fafb;
            border-left: 3px solid #059669;
            padding: 16px 20px;
            border-radius: 0 6px 6px 0;
            margin: 20px 0;
        }
        .message-box p {
            font-size: 15px;
            color: #374151;
            margin: 0;
            font-style: italic;
        }
        .cta-button {
            display: inline-block;
            background-color: #059669;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 8px;
        }
        .footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-card">
            <div class="header">
                <h1>Your Gallery is Ready</h1>
                <p>{{ $galleryName }}</p>
            </div>

            <p style="font-size: 15px;">
                Great news — your gallery <strong>{{ $galleryName }}</strong> has been delivered and is ready for you to view!
            </p>

            @if($deliveryMessage)
                <div class="message-box">
                    <p>{!! nl2br(e($deliveryMessage)) !!}</p>
                </div>
            @endif

            <hr class="divider">

            <p style="font-size: 14px; color: #6b7280;">
                Delivered on {{ \Carbon\Carbon::parse($deliveredAt)->format('M j, Y \a\t g:i A') }}
            </p>

            <a href="{{ $viewerUrl }}" class="cta-button">View Your Gallery</a>

            <div class="footer">
                You're receiving this because you have access to this gallery.
                Click the button above to view your images.
            </div>
        </div>
    </div>
</body>
</html>
