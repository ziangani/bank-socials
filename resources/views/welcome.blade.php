<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.friendly_name', 'Social Banking') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-blue-600 text-white py-6">
            <div class="container mx-auto px-4">
                <h1 class="text-3xl font-bold">{{ config('app.friendly_name', 'Social Banking') }}</h1>
                <p class="mt-2">Banking Made Social</p>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <!-- Hero Section -->
            <section class="text-center mb-12">
                <h2 class="text-4xl font-bold mb-4">Welcome to Social Banking</h2>
                <p class="text-xl text-gray-600 mb-8">Access your banking services through WhatsApp and USSD</p>
                
                <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                    <!-- WhatsApp Card -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-2xl font-semibold mb-4">WhatsApp Banking</h3>
                        <p class="text-gray-600 mb-4">Access your account through WhatsApp</p>
                        <div class="mb-4">
                            <p class="font-semibold">How to get started:</p>
                            <ol class="text-left list-decimal list-inside">
                                <li>Save our number: {{ config('whatsapp.phone_number', '+1234567890') }}</li>
                                <li>Send "Hi" to start</li>
                                <li>Follow the registration process</li>
                            </ol>
                        </div>
                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', config('whatsapp.phone_number', '+1234567890')) }}" 
                           class="inline-block bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600"
                           target="_blank">
                            Start WhatsApp Banking
                        </a>
                    </div>

                    <!-- USSD Card -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h3 class="text-2xl font-semibold mb-4">USSD Banking</h3>
                        <p class="text-gray-600 mb-4">Quick banking with USSD</p>
                        <div class="mb-4">
                            <p class="font-semibold">How to use:</p>
                            <ol class="text-left list-decimal list-inside">
                                <li>Dial {{ config('social-banking.channels.ussd.service_code', '*123#') }}</li>
                                <li>Select your preferred service</li>
                                <li>Follow the prompts</li>
                            </ol>
                        </div>
                        <a href="{{ url('ussd/simulator') }}" 
                           class="inline-block bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                            Try USSD Simulator
                        </a>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="mb-12">
                <h2 class="text-3xl font-bold text-center mb-8">Available Services</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <!-- Money Transfer -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold mb-2">Money Transfer</h3>
                        <ul class="list-disc list-inside text-gray-600">
                            <li>Internal transfers</li>
                            <li>Bank-to-bank transfers</li>
                            <li>Mobile money transfers</li>
                        </ul>
                    </div>

                    <!-- Bill Payments -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold mb-2">Bill Payments</h3>
                        <ul class="list-disc list-inside text-gray-600">
                            <li>Utility bills</li>
                            <li>Service providers</li>
                            <li>Government payments</li>
                        </ul>
                    </div>

                    <!-- Account Services -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-xl font-semibold mb-2">Account Services</h3>
                        <ul class="list-disc list-inside text-gray-600">
                            <li>Balance inquiry</li>
                            <li>Mini statements</li>
                            <li>Full statements</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Developer Section -->
            <section class="text-center">
                <h2 class="text-3xl font-bold mb-4">For Developers</h2>
                <p class="text-gray-600 mb-4">Integrate our services into your applications</p>
                <div class="space-x-4">
                    <a href="{{ url('docs') }}" 
                       class="inline-block bg-gray-800 text-white px-6 py-2 rounded-lg hover:bg-gray-900">
                        API Documentation
                    </a>
                    <a href="https://github.com/yourusername/social-banking" 
                       class="inline-block bg-gray-800 text-white px-6 py-2 rounded-lg hover:bg-gray-900"
                       target="_blank">
                        GitHub Repository
                    </a>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-6">
            <div class="container mx-auto px-4">
                <div class="grid md:grid-cols-3 gap-8">
                    <div>
                        <h4 class="font-semibold mb-2">Contact Us</h4>
                        <p>Email: support@example.com</p>
                        <p>Phone: +1234567890</p>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">Quick Links</h4>
                        <ul>
                            <li><a href="{{ url('docs') }}" class="hover:text-blue-300">API Documentation</a></li>
                            <li><a href="{{ url('ussd/simulator') }}" class="hover:text-blue-300">USSD Simulator</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">Legal</h4>
                        <ul>
                            <li><a href="#" class="hover:text-blue-300">Terms of Service</a></li>
                            <li><a href="#" class="hover:text-blue-300">Privacy Policy</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 text-center">
                    <p>&copy; {{ date('Y') }} {{ config('app.friendly_name', 'Social Banking') }}. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
