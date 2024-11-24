<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Welcome to Social Banking</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Tailwind CSS -->
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

        <style>
            .feature-list {
                padding: 0;
            }
            .feature-list li {
                margin: 10px 0;
                padding: 10px;
                background-color: #f4f4f4;
                border-radius: 3px;
            }
        </style>
    </head>
    <body class="bg-gray-100 text-black font-sans antialiased">
        <div class="min-h-screen flex items-center justify-center">
            <div class="text-center bg-gray-50 p-10 rounded-lg mb-10">
                <h1 class="text-5xl font-extrabold mb-6 text-black">Welcome to Social Banking</h1>
                <hr/>
                <p class="text-xl mb-6 text-gray-800">Your gateway to a new era of financial connectivity.</p>
                <hr/>
                {{-- <h2 class="text-2xl texl- font-semibold mb-4 text-gray-900">Features:</h2> --}}
                <ol class="feature-list text-left">
                    <li>Seamless integration with multiple financial platforms</li>
                    <li>Real-time transaction processing</li>
                    <li>Advanced security protocols</li>
                    <li>User-friendly interface</li>
                    <li>24/7 customer support</li>
                </ol>
            </div>
        </div>
    </body>
</html>
