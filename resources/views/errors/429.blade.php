@extends('errors.app')

@section('content')
@php
$title = '429 - Too Many Requests';
$message = 'You have made too many requests. Please wait a while before trying again.';
$icon = '<svg class="w-24 h-24 text-orange-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
</svg>';
$code = '429';
$retry_url = url()->previous();
@endphp
