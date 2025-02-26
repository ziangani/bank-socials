<?php

namespace App\Http\Controllers;

use App\Channels\USSDChannel;
use App\Models\WhatsAppSessions;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class USSDController extends Controller
{
    protected USSDChannel $ussdChannel;
    protected TransactionService $transactionService;

    public function __construct(
        USSDChannel $ussdChannel,
        TransactionService $transactionService
    ) {
        $this->ussdChannel = $ussdChannel;
        $this->transactionService = $transactionService;
    }

    /**
     * Handle USSD request
     */
    public function handle(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'sessionId' => 'required|string',
                'phoneNumber' => 'required|string',
                'text' => 'string|nullable',
                'serviceCode' => 'required|string'
            ]);

            // Process request through USSD channel
            $response = $this->ussdChannel->processRequest([
                'sessionId' => $request->sessionId,
                'phoneNumber' => $request->phoneNumber,
                'text' => $request->text,
                'serviceCode' => $request->serviceCode
            ]);

            // Format response for USSD
            $formattedResponse = $this->ussdChannel->formatResponse($response);

            return response()->json($formattedResponse);

        } catch (\Exception $e) {
            Log::error('USSD request error: ' . $e->getMessage());
            
            // Return error in USSD format
            return response()->json([
                'message' => 'System error. Please try again.',
                'type' => 'END'
            ]);
        }
    }

    /**
     * Simulate USSD session for testing
     */
    public function simulate(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'input' => 'required|string',
                'phoneNumber' => 'required|string'
            ]);

            // Generate session ID for simulation if not provided
            $sessionId = $request->input('sessionId', 'SIM_' . uniqid());

            // For new sessions (when input is 'start'), pass empty text
            $text = $request->input === 'start' ? '' : $request->input;

            // Process request through USSD channel
            $response = $this->ussdChannel->processRequest([
                'sessionId' => $sessionId,
                'phoneNumber' => $request->phoneNumber,
                'text' => $text,
                'serviceCode' => '*123#'
            ]);

            // Format response for simulator
            $formattedResponse = $this->ussdChannel->formatResponse($response);

            // Get current session state
            $session = WhatsAppSessions::getActiveSession($sessionId);
            $nextState = $session ? $session->state : null;

            return response()->json([
                'sessionId' => $sessionId,
                'response' => $formattedResponse,
                'nextState' => $nextState
            ]);

        } catch (\Exception $e) {
            Log::error('USSD simulation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Simulation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * End USSD session
     */
    public function endSession(Request $request)
    {
        try {
            $request->validate([
                'sessionId' => 'required|string'
            ]);

            $this->ussdChannel->endSession($request->sessionId);

            return response()->json([
                'message' => 'Session ended successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('USSD session end error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to end session',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session status
     */
    public function sessionStatus(Request $request)
    {
        try {
            $request->validate([
                'sessionId' => 'required|string'
            ]);

            $session = WhatsAppSessions::getActiveSession($request->sessionId);

            return response()->json([
                'sessionId' => $request->sessionId,
                'isValid' => $session ? $session->isActive() : false,
                'state' => $session ? $session->state : null
            ]);

        } catch (\Exception $e) {
            Log::error('USSD session status error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get session status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
