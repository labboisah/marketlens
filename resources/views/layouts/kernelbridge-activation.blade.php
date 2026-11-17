<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b3d6e">
    <title>KernelBridge license activation</title>
    @vite('resources/js/account-license-activation.js')
</head>
<body>
    {{ $slot }}
</body>
</html>
