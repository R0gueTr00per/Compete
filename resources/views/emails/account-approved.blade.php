<x-mail::message>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:24px;">
<tr>
<td bgcolor="#15803d" align="center" style="padding:22px 20px;background-color:#15803d;">
<p style="font-size:28px;margin:0;line-height:1;color:#bbf7d0;">✓</p>
<p style="color:#ffffff;font-size:19px;font-weight:700;margin:8px 0 4px 0;line-height:1.2;">Account Approved</p>
<p style="color:rgba(255,255,255,0.75);font-size:14px;margin:0;">{{ $org ? $org->name : 'Compete' }}</p>
</td>
</tr>
</table>

Hi {{ $recipientName }},

{{ $org ? "Your membership for **{$org->name}** has been approved." : 'Great news — your account has been approved and is now active.' }} You can now log in and enrol in competitions.

<x-mail::button :url="$loginUrl">
Log in now
</x-mail::button>

@include('emails.partials.email-footer')
</x-mail::message>
