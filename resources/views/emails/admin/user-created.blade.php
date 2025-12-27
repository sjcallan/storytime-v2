<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
        }

        /* Email client specific fixes */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
        }

        /* Brand Colors - Storytime gradient: #f53003 to #F8B803 */
        .brand-gradient {
            background: linear-gradient(135deg, #f53003 0%, #F8B803 100%);
        }

        /* Typography */
        .font-sans {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <!-- Preview text -->
    <div style="display: none; max-height: 0; overflow: hidden;">
        New user registered: {{ $newUser->name }} ({{ $newUser->email }}) has joined {{ config('app.name') }}.
    </div>

    <!-- Email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);">
                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f53003 0%, #F8B803 100%); padding: 32px 40px; text-align: center;">
                            <!-- Logo area -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <!-- Sapling icon representation -->
                                        <div style="display: inline-block; width: 48px; height: 48px; background-color: rgba(255, 255, 255, 0.2); border-radius: 12px; margin-bottom: 16px; line-height: 48px; font-size: 24px;">
                                            📚
                                        </div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px;">
                                            Storytime
                                        </h1>
                                        <p style="margin: 8px 0 0 0; color: rgba(255, 255, 255, 0.9); font-size: 14px; font-weight: 500;">
                                            New User Registration
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            <p style="margin: 0 0 24px 0; color: #18181b; font-size: 16px; line-height: 1.6;">
                                Hello {{ $admin->name }},
                            </p>

                            <p style="margin: 0 0 24px 0; color: #3f3f46; font-size: 16px; line-height: 1.6;">
                                A new user has registered on <strong style="color: #18181b;">{{ config('app.name') }}</strong>. Here are their details:
                            </p>

                            <!-- User details card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #fafafa; border-radius: 12px; border: 1px solid #e4e4e7; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <!-- User avatar placeholder -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="width: 56px; vertical-align: top;">
                                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f53003 0%, #F8B803 100%); border-radius: 50%; text-align: center; line-height: 48px; color: #ffffff; font-size: 20px; font-weight: 600;">
                                                        {{ strtoupper(substr($newUser->name, 0, 1)) }}
                                                    </div>
                                                </td>
                                                <td style="vertical-align: top;">
                                                    <h3 style="margin: 0 0 4px 0; color: #18181b; font-size: 18px; font-weight: 600;">
                                                        {{ $newUser->name }}
                                                    </h3>
                                                    <p style="margin: 0; color: #71717a; font-size: 14px;">
                                                        New member
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Divider -->
                                        <div style="height: 1px; background-color: #e4e4e7; margin: 20px 0;"></div>

                                        <!-- Details list -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <!-- Email -->
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 100px; color: #71717a; font-size: 14px; font-weight: 500;">
                                                                Email
                                                            </td>
                                                            <td style="color: #18181b; font-size: 14px;">
                                                                <a href="mailto:{{ $newUser->email }}" style="color: #f53003; text-decoration: none;">
                                                                    {{ $newUser->email }}
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <!-- User ID -->
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 100px; color: #71717a; font-size: 14px; font-weight: 500;">
                                                                User ID
                                                            </td>
                                                            <td style="color: #18181b; font-size: 14px; font-family: 'SF Mono', SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace;">
                                                                {{ $newUser->id }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <!-- Registration date -->
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 100px; color: #71717a; font-size: 14px; font-weight: 500;">
                                                                Registered
                                                            </td>
                                                            <td style="color: #18181b; font-size: 14px;">
                                                                {{ $newUser->created_at->format('M j, Y \a\t g:i A') }}
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <!-- Email verified status -->
                                            <tr>
                                                <td style="padding: 8px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 100px; color: #71717a; font-size: 14px; font-weight: 500;">
                                                                Verified
                                                            </td>
                                                            <td style="font-size: 14px;">
                                                                @if($newUser->email_verified_at)
                                                                    <span style="display: inline-block; padding: 2px 10px; background-color: #dcfce7; color: #166534; border-radius: 9999px; font-size: 12px; font-weight: 500;">
                                                                        Verified
                                                                    </span>
                                                                @else
                                                                    <span style="display: inline-block; padding: 2px 10px; background-color: #fef3c7; color: #92400e; border-radius: 9999px; font-size: 12px; font-weight: 500;">
                                                                        Pending
                                                                    </span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Info text -->
                            <p style="margin: 0 0 24px 0; color: #71717a; font-size: 14px; line-height: 1.6;">
                                This is an automated notification. The user can now access the platform and start creating their stories.
                            </p>

                            <!-- CTA Button (optional - link to admin dashboard) -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.url') }}" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #f53003 0%, #F8B803 100%); color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 8px; box-shadow: 0 2px 4px rgba(245, 48, 3, 0.3);">
                                            View Dashboard
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #fafafa; padding: 24px 40px; border-top: 1px solid #e4e4e7;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px 0; color: #71717a; font-size: 13px;">
                                            © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                                        </p>
                                        <p style="margin: 0; color: #a1a1aa; font-size: 12px;">
                                            You're receiving this email because you're an administrator.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

