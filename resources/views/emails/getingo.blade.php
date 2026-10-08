<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $title ?? 'Getingo' }}</title>
</head>
<body style="margin:0;background:#f2f6ff;font-family:Arial,Helvetica,sans-serif;color:#102449">
<div style="display:none;max-height:0;overflow:hidden">{{ $preheader ?? '' }}</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f6ff;padding:28px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#fff;border:1px solid #dce8f7;border-radius:22px;overflow:hidden;box-shadow:0 18px 55px rgba(18,55,108,.10)">
<tr><td style="padding:28px 34px;background:linear-gradient(135deg,#071a3a,#0d2d62);color:#fff">
<div style="font-size:14px;font-weight:800;letter-spacing:.12em;color:#8fc3ff">GETINGO</div>
<h1 style="margin:10px 0 0;font-size:28px;line-height:1.2">{{ $title }}</h1>
</td></tr>
<tr><td style="padding:34px">
<p style="margin:0 0 16px;font-size:18px;font-weight:700">Szia {{ $name ?? '' }}!</p>
<p style="margin:0 0 18px;line-height:1.7;color:#405879">{{ $intro ?? '' }}</p>
@if(!empty($lines))
@foreach($lines as $line)<p style="margin:0 0 14px;line-height:1.7;color:#405879">{{ $line }}</p>@endforeach
@endif
@if(!empty($buttonText) && !empty($buttonUrl))
<table role="presentation" cellspacing="0" cellpadding="0" style="margin:26px 0"><tr><td style="border-radius:12px;background:#1677ff"><a href="{{ $buttonUrl }}" style="display:inline-block;padding:14px 22px;color:#fff;text-decoration:none;font-weight:800">{{ $buttonText }}</a></td></tr></table>
@endif
<p style="margin:28px 0 0;padding-top:20px;border-top:1px solid #e3ebf6;color:#7a8aa2;font-size:13px;line-height:1.6">Getingo · Tanulj, gyakorolj, fejlődj.</p>
</td></tr></table>
</td></tr></table>
</body></html>
