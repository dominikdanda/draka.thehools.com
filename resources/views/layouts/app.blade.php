<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'The Hools')</title>

    <link rel="stylesheet" href="{{ asset('assets/css/thehools.css') }}">
</head>
<body>
@yield('content')

<script src="{{ asset('assets/js/thehools.js') }}"></script>
</body>
</html>
