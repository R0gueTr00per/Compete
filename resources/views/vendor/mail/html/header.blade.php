@props(['url'])
<tr>
<td class="header" style="background-color: #1a3564; padding: 24px 40px;">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
    <table cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
    <tr>
        <td style="padding-right: 12px; vertical-align: middle; width: 55px;">
            <img src="{{ asset('images/logo-email.png') }}" alt="Kompetic" width="55" height="60" style="width: 55px; height: 60px; max-width: 55px; display: block;">
        </td>
        <td style="vertical-align: middle; text-align: left;">
            <span style="display: block; font-size: 24px; font-weight: 800; letter-spacing: 0.12em; color: #ffffff; line-height: 1;">KOMPETIC</span>
            <span style="display: block; font-size: 9px; font-weight: 500; letter-spacing: 0.2em; color: rgba(255,255,255,0.65); margin-top: 3px; text-transform: uppercase;">Tournament Management</span>
        </td>
    </tr>
    </table>
</a>
</td>
</tr>
