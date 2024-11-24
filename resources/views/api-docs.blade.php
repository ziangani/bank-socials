<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Banking API Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-tomorrow.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-json.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-blue-600 text-white py-6">
            <div class="container mx-auto px-4">
                <h1 class="text-3xl font-bold">Social Banking API</h1>
                <p class="mt-2">API Documentation for WhatsApp and USSD Banking Services</p>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-8">
            <!-- Introduction -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Introduction</h2>
                <p class="text-gray-700">
                    This API documentation provides information about the endpoints available for integrating with our 
                    social banking platform. The API supports both WhatsApp and USSD channels for various banking operations.
                </p>
            </section>

            <!-- Authentication -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Authentication</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">Card Registration</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Register a new user with card details:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/auth/register/card",
    "body": {
        "card_number": "1234567890123456",
        "expiry": "12/25",
        "cvv": "123",
        "phone_number": "260971234567"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Account Registration</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Register a new user with account details:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/auth/register/account",
    "body": {
        "account_number": "1234567890",
        "id_number": "123456/78/9",
        "phone_number": "260971234567"
    }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Money Transfer -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Money Transfer</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">Internal Transfer</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Transfer money to another account:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/transfer/internal",
    "body": {
        "sender": "1234567890",
        "recipient": "0987654321",
        "amount": 1000.00,
        "pin": "1234"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Bank Transfer</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Transfer money to another bank:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/transfer/bank",
    "body": {
        "sender": "1234567890",
        "bank_name": "Example Bank",
        "bank_account": "0987654321",
        "amount": 1000.00,
        "pin": "1234"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Mobile Money Transfer</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Transfer money to mobile money:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/transfer/mobile",
    "body": {
        "sender": "1234567890",
        "recipient": "260971234567",
        "amount": 1000.00,
        "pin": "1234"
    }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Bill Payments -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Bill Payments</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">Validate Bill Account</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Validate a bill account number:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/bills/validate",
    "body": {
        "account_number": "1234567890",
        "bill_type": "ELECTRICITY"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Pay Bill</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Make a bill payment:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/bills/pay",
    "body": {
        "payer": "1234567890",
        "bill_account": "0987654321",
        "bill_type": "ELECTRICITY",
        "amount": 1000.00,
        "pin": "1234"
    }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Account Services -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Account Services</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">Balance Inquiry</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Check account balance:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/account/balance",
    "body": {
        "account_number": "1234567890",
        "pin": "1234"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Mini Statement</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Get mini statement:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/account/mini-statement",
    "body": {
        "account_number": "1234567890",
        "pin": "1234"
    }
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">Full Statement</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">Get full statement:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /api/account/statement",
    "body": {
        "account_number": "1234567890",
        "pin": "1234",
        "start_date": "2024-01-01",
        "end_date": "2024-03-21",
        "per_page": 20
    }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Channel-Specific Endpoints -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Channel-Specific Endpoints</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">WhatsApp Webhook</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">WhatsApp webhook endpoint:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /whatsapp/webhook",
    "description": "Handles incoming WhatsApp messages"
}</code></pre>
                    </div>

                    <h3 class="text-xl font-semibold mb-4">USSD Handler</h3>
                    <div class="mb-4">
                        <p class="text-gray-700 mb-2">USSD request handler:</p>
                        <pre><code class="language-json">{
    "endpoint": "POST /ussd/handle",
    "body": {
        "sessionId": "session123",
        "phoneNumber": "260971234567",
        "text": "1",
        "serviceCode": "*123#"
    }
}</code></pre>
                    </div>
                </div>
            </section>

            <!-- Error Handling -->
            <section class="mb-12">
                <h2 class="text-2xl font-bold mb-4">Error Handling</h2>
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-gray-700 mb-4">All endpoints return errors in the following format:</p>
                    <pre><code class="language-json">{
    "status": "error",
    "message": "Detailed error message",
    "code": "ERROR_CODE"  // Optional error code
}</code></pre>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-6">
            <div class="container mx-auto px-4">
                <p>&copy; {{ date('Y') }} Social Banking. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>
