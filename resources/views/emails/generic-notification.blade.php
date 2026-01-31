<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Notification' }}</title>
</head>
<body>
    <h1>{{ $title ?? 'Notification' }}</h1>
    <p>{!! nl2br(e($message ?? '')) !!}</p>
</body>
</html>