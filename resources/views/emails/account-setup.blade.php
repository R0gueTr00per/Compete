<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#2563a8" align="center" style="padding:22px 20px;background-color:#2563a8;">
<p style="font-size:28px;margin:0;line-height:1;">🔑</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Welcome{{ $org ? ' to ' . $org->name : '' }}</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">Set up your account to get started</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

{{ $org ? "An account has been created for you on **{$org->name}**." : 'An account has been created for you on Compete.' }} Click the button below to set your password and access your account.

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#0f766e" style="padding:9px 16px;background-color:#0f766e;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Next Step</p>
</td></tr>
<tr><td style="padding:16px;">
<p style="margin:0;font-size:14px;color:#374151;">Set your password to activate your account and access the competitor portal — view upcoming competitions and manage your registrations.</p>
</td></tr>
</table>

<x-mail::button :url="$resetUrl">
Set your password
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
