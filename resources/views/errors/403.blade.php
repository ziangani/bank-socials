@extends('errors.app')

@section('content')
@php
$title = '403 - Access Denied';
$message = 'You do not have permission to access this resource.';
$icon = '<svg class="w-24 h-24 text-yellow-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
</svg>';
$code = '403';
@endphp
