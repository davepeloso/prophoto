<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isFirstView ? 'Gallery Viewed' : 'Gallery Milestone' }}</title>
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
        .stats-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .stats-table td {
            padding: 10px 0;
            font-size: 15px;
        }
        .stats-table td:first-child {
            color: #6b7280;
            width: 140px;
        }
        .stats-table td:last-child {
            font-weight: 500;
            color: #111827;
        }
        .cta-button {
            display: inline-block;
            background-color: #059669;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 500;
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
                <h1>{{ $isFirstView ? 'Gallery Viewed' : 'Gallery Milestone' }}</h1>
                <p>{{ $galleryName }}</p>
            </div>

            @if($isFirstView)
                <p style="font-size: 15px;">
                    Great news — <strong>{{ $viewedByEmail }}</strong> just viewed <strong>{{ $galleryName }}</strong> for the first time!
                </p>
            @else
                <p style="font-size: 15px;">
                    <strong>{{ $galleryName }}</strong> has been viewed <strong>{{ $viewCount }} times</strong> by <strong>{{ $viewedByEmail }}</strong>.
                </p>
            @endif

            <hr class="divider">

            <table class="stats-table">
                <tr>
                    <td>Viewer</td>
                    <td>{{ $viewedByEmail }}</td>
                </tr>
                <tr>
                    <td>Total views</td>
                    <td>{{ $viewCount }}</td>
                </tr>
                <tr>
                    <td>Viewed at</td>
                    <td>{{ \Carbon\Carbon::parse($viewedAt)->format('M j, Y \a\t g:i A') }}</td>
                </tr>
            </table>

            <hr class="divider">

            <a href="{{ $dashboardUrl }}" class="cta-button">View in Dashboard</a>

            <div class="footer">
                You're receiving this because a client viewed one of your galleries.
            </div>
        </div>
    </div>
</body>
</html>
