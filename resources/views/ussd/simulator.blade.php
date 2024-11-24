<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Simulator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .ussd-screen {
            font-family: monospace;
            background-color: #1a1a1a;
            color: #33ff33;
            padding: 20px;
            border-radius: 5px;
            min-height: 200px;
            white-space: pre-wrap;
        }
        .blink {
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            50% { opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6">
            <h1 class="text-2xl font-bold mb-4">Social Banking - USSD Simulator</h1>

            <!-- Phone Number Input -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="tel" id="phoneNumber"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="260971234567"
                    value="260971234567">
            </div>

            <!-- USSD Screen -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">USSD Screen</label>
                <div id="ussdScreen" class="ussd-screen">
Welcome to USSD Simulator

Press "Start Session" to begin<span class="blink">_</span></div>
            </div>

            <!-- Input Area -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Input</label>
                <input type="text" id="userInput"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter your response">
            </div>

            <!-- Buttons -->
            <div class="flex space-x-2">
                <button onclick="startSession()" id="startButton"
                    class="flex-1 bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                    Start Session
                </button>
                <button onclick="sendInput()" id="sendButton"
                    class="flex-1 bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 hidden">
                    Send
                </button>
                <button onclick="cancelSession()" id="cancelButton"
                    class="flex-1 bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 hidden">
                    Cancel
                </button>
            </div>

            <!-- Session Info -->
            <div class="mt-4 text-sm text-gray-600">
                <p>Session ID: <span id="sessionId">-</span></p>
                <p>State: <span id="sessionState">-</span></p>
            </div>
        </div>
    </div>

    <script>
        let currentSessionId = null;

        async function startSession() {
            const phoneNumber = document.getElementById('phoneNumber').value;
            try {
                const response = await fetch('/ussd/simulate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        phoneNumber: phoneNumber,
                        input: ''
                    })
                });

                const data = await response.json();
                updateScreen(data);

                // Show/hide buttons
                document.getElementById('startButton').classList.add('hidden');
                document.getElementById('sendButton').classList.remove('hidden');
                document.getElementById('cancelButton').classList.remove('hidden');

                // Focus input
                document.getElementById('userInput').focus();
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('ussdScreen').textContent = 'Error: Could not start session';
            }
        }

        async function sendInput() {
            const phoneNumber = document.getElementById('phoneNumber').value;
            const input = document.getElementById('userInput').value;

            if (!input.trim()) {
                return;
            }

            try {
                const response = await fetch('/ussd/simulate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        phoneNumber: phoneNumber,
                        input: input,
                        sessionId: currentSessionId
                    })
                });

                const data = await response.json();
                updateScreen(data);
                document.getElementById('userInput').value = '';
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('ussdScreen').textContent = 'Error: Could not process input';
            }
        }

        async function cancelSession() {
            if (!currentSessionId) return;

            try {
                await fetch('/ussd/end-session', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        sessionId: currentSessionId
                    })
                });

                document.getElementById('ussdScreen').textContent = 'Session ended\n\nPress "Start Session" to begin';
                document.getElementById('sessionId').textContent = '-';
                document.getElementById('sessionState').textContent = '-';
                currentSessionId = null;

                // Show/hide buttons
                document.getElementById('startButton').classList.remove('hidden');
                document.getElementById('sendButton').classList.add('hidden');
                document.getElementById('cancelButton').classList.add('hidden');

            } catch (error) {
                console.error('Error:', error);
                document.getElementById('ussdScreen').textContent = 'Error: Could not end session';
            }
        }

        function updateScreen(data) {
            const screen = document.getElementById('ussdScreen');
            screen.textContent = data.response.message;

            if (data.sessionId) {
                currentSessionId = data.sessionId;
                document.getElementById('sessionId').textContent = data.sessionId;
            }

            if (data.nextState) {
                document.getElementById('sessionState').textContent = data.nextState;
            }

            if (data.response.type === 'END') {
                screen.textContent += '\n\nSession ended\n\nPress "Start Session" to begin';
                currentSessionId = null;
                document.getElementById('sessionId').textContent = '-';
                document.getElementById('sessionState').textContent = '-';

                // Show/hide buttons
                document.getElementById('startButton').classList.remove('hidden');
                document.getElementById('sendButton').classList.add('hidden');
                document.getElementById('cancelButton').classList.add('hidden');
            } else {
                screen.textContent += '\n\n' + (data.response.type === 'CON' ? '_' : '');
            }
        }

        // Handle Enter key in input field
        document.getElementById('userInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && currentSessionId) {
                sendInput();
            }
        });
    </script>
</body>
</html>
