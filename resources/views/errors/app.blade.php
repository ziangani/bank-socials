<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Error' }} - {{ config('app.friendly_name', 'Social Banking') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-blue-600 text-white py-6">
        <div class="container mx-auto px-4">
            <h1 class="text-3xl font-bold">{{ config('app.friendly_name', 'Social Banking') }}</h1>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8 flex items-center justify-center">
        <div class="max-w-lg w-full">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <!-- Error Icon -->
                <div class="mb-6">
                    @if(isset($icon))
                        {!! $icon !!}
                    @else
                        <svg class="w-24 h-24 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    @endif
                </div>

                <!-- Error Title -->
                <h1 class="text-4xl font-bold text-gray-800 mb-4">
                    {{ $title ?? 'Oops! Something went wrong' }}
                </h1>

                <!-- Error Message -->
                <p class="text-gray-600 mb-8">
                    {{ $message ?? 'We encountered an error while processing your request.' }}
                </p>

                <!-- Error Code -->
                @if(isset($code))
                    <p class="text-sm text-gray-500 mb-8">
                        Error Code: {{ $code }}
                    </p>
                @endif

                <!-- Action Buttons -->
                <div class="space-x-4">
                    <a href="{{ url('/') }}" 
                       class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        Return Home
                    </a>
                    @if(isset($retry_url))
                        <a href="{{ $retry_url }}" 
                           class="inline-block bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700">
                            Try Again
                        </a>
                    @endif
                </div>

                <!-- Support Information -->
                <div class="mt-8 text-sm text-gray-500">
                    <p>Need help? Contact our support:</p>
                    <p>Email: support@example.com</p>
                    <p>Phone: +1234567890</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="container mx-auto px-4 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.friendly_name', 'Social Banking') }}. All rights reserved.</p>
        </div>
    </footer>

    @if(config('app.debug') && isset($exception))
        <!-- Debug Information (only shown in debug mode) -->
        <div class="fixed bottom-0 left-0 right-0 bg-gray-900 text-white p-4">
            <div class="container mx-auto">
                <details class="text-sm">
                    <summary class="cursor-pointer">Debug Information</summary>
                    <pre class="mt-2 overflow-x-auto">{{ $exception }}</pre>
                </details>
            </div>
        </div>
    @endif
</body>
</html>
