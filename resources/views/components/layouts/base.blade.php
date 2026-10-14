@props(['title' => null, 'description' => 'Functional fitness for the whole family in Durbanville. 45-minute classes where kids and adults train together.'])

@php($pageTitle = $title ? $title.' · OptiFit' : 'OptiFit · Functional Fitness Training')

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0D0918">
    <meta name="description" content="{{ $description }}">
    <title>{{ $pageTitle }}</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ asset('images/brand/og.jpg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body {{ $attributes->class('min-h-dvh') }}>
    {{ $slot }}

    <x-toast />
    @livewireScripts
</body>
</html>
