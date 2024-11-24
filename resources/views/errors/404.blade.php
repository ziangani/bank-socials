@extends('errors.app')

@section('content')
@php
$title = '404 - Page Not Found';
$message = 'The page you are looking for could not be found.';
$icon = '<svg class="w-24 h-24 text-blue-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
</svg>';
$code = '404';
@endphp
