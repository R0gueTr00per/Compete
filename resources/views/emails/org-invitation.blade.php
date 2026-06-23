<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#6d28d9" align="center" style="padding:22px 20px;background-color:#6d28d9;">
<p style="font-size:28px;margin:0;line-height:1;">✉</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">You've been invited</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $org->name }} on Kompetic</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

@if ($isNewUser)
You've been invited to join **{{ $org->name }}** as a {{ $roleLabel }} on Kompetic. Click the button below to set up your account and get started.
@else
You've been added to **{{ $org->name }}** as a {{ $roleLabel }} on Kompetic.
@endif

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:20px 0;border:1px solid #dde5f0;">
<tr><td bgcolor="#6d28d9" style="padding:9px 16px;background-color:#6d28d9;">
<p style="color:#ffffff;font-size:11px;font-weight:700;margin:0;text-transform:uppercase;letter-spacing:0.08em;">Invitation Details</p>
</td></tr>
<tr><td style="padding:4px 16px 8px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;width:38%;vertical-align:top;">Organisation</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;vertical-align:top;">{{ $org->name }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Role</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">{{ ucfirst($roleLabel) }}</td>
</tr>
<tr>
<td style="padding:8px 12px 8px 0;color:#64748b;font-size:13px;border-top:1px solid #f1f5f9;vertical-align:top;">Expires</td>
<td style="padding:8px 0;color:#1a3564;font-size:14px;font-weight:600;border-top:1px solid #f1f5f9;vertical-align:top;">7 days</td>
</tr>
</table>
</td></tr>
</table>

<x-mail::button :url="$acceptUrl">
{{ $isNewUser ? 'Accept Invitation' : 'Access Organisation' }}
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
