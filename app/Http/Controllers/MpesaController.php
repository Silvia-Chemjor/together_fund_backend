<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class MpesaController extends Controller
{
    // Get Daraja access token
    private function getAccessToken(){
        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->withBasicAuth(
                    config('services.mpesa.consumer_key'),
                    config('services.mpesa.consumer_secret')
                )
                ->get(
                    config('services.mpesa.base_url') .
                    '/oauth/v1/generate?grant_type=client_credentials'
                );

            if ($response->failed()) {
                return null;
            }

            return $response->json('access_token');

        } catch (ConnectionException $e) {
            Log::warning('Daraja token connection failed.');
            return null;
        }
    }
    // Test Daraja authentication
    public function getToken(){
        $token = $this->getAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'Could not authenticate with Daraja.'
            ], 502);
        }

        return response()->json([
            'message' => 'Daraja authentication successful.'
        ], 200);
    }
    // Send STK Push
    public function stkPush(Request $request){
        $userId = $request->user()->id;

        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^254[17][0-9]{8}$/'],
            'amount' => 'required|integer|min:1',
            'group_id' => 'required|exists:groups,id',
            'idempotency_key' => 'required|string|max:100|regex:/^[A-Za-z0-9_-]+$/'
        ]);

        $phone = $validated['phone'];
        $amount = $validated['amount'];
        $key = $validated['idempotency_key'];

        // Check if this payment request already exists
        $existing = Payment::where('idempotency_key', $key)->first();

        if ($existing) {

            if (
                $existing->phone_number != $phone ||
                $existing->amount != $amount ||
                $existing->group_id != $validated['group_id'] ||
                $existing->user_id != $userId
            ) {
                return response()->json([
                    'message' => 'This request key was already used with different details.'
                ], 409);
            }

            return response()->json([
                'message' => 'This request was already submitted. No new prompt was sent.',
                'payment_id' => $existing->id,
                'status' => $existing->status,
                'checkout_request_id' => $existing->checkout_request_id
            ], 200);
        }

        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $baseUrl = config('services.mpesa.base_url');
        $callbackUrl = config('services.mpesa.callback_url');

        if (!$shortcode || !$passkey || !$baseUrl || !$callbackUrl) {
            return response()->json([
                'message' => 'Daraja configuration is incomplete.'
            ], 500);
        }

        // Save the payment before sending the request
        $payment = Payment::create([
            'user_id' => $userId,
            'group_id' => $validated['group_id'],
            'idempotency_key' => $key,
            'phone_number' => $phone,
            'amount' => $amount,
            'status' => 'Initiating'
        ]);

        $token = $this->getAccessToken();

        if (!$token) {
            $payment->update([
                'status' => 'Failed'
            ]);

            return response()->json([
                'message' => 'Could not authenticate with Daraja.'
            ], 502);
        }

        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $password = base64_encode(
            $shortcode . $passkey . $timestamp
        );

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withToken($token)
                ->post($baseUrl . '/mpesa/stkpush/v1/processrequest', [
                    'BusinessShortCode' => $shortcode,
                    'Password' => $password,
                    'Timestamp' => $timestamp,
                    'TransactionType' => 'CustomerPayBillOnline',
                    'Amount' => $amount,
                    'PartyA' => $phone,
                    'PartyB' => $shortcode,
                    'PhoneNumber' => $phone,
                    'CallBackURL' => $callbackUrl,
                    'AccountReference' => 'PracticeTest',
                    'TransactionDesc' => 'Test payment'
                ]);

        } catch (ConnectionException $e) {

            $payment->update([
                'status' => 'Unknown'
            ]);

            Log::warning('STK Push outcome is unknown.', [
                'payment_id' => $payment->id
            ]);

            return response()->json([
                'message' => 'The result is uncertain. Check this payment before attempting another.',
                'payment_id' => $payment->id
            ], 504);
        }

        $data = $response->json();

        if (!is_array($data)) {
            $payment->update([
                'status' => 'Unknown'
            ]);

            return response()->json([
                'message' => 'Daraja returned an unreadable response. Check payment status before retrying.',
                'payment_id' => $payment->id
            ], 502);
        }

        if ($response->successful() && !empty($data['CheckoutRequestID'])) {

            $payment->update([
                'checkout_request_id' => $data['CheckoutRequestID'],
                'merchant_request_id' => $data['MerchantRequestID'] ?? null,
                'status' => 'Pending'
            ]);

            return response()->json([
                'message' => 'STK Push request accepted.',
                'payment_id' => $payment->id,
                'checkout_request_id' => $data['CheckoutRequestID'],
                'status' => 'Pending'
            ], 200);
        }

        $payment->update([
            'status' => 'Failed'
        ]);

        Log::warning('STK Push was not accepted.', [
            'payment_id' => $payment->id,
            'http_status' => $response->status()
        ]);

        return response()->json([
            'message' => $data['ResponseDescription']
                ?? 'Daraja did not accept the STK Push request.'
        ], $response->successful() ? 502 : $response->status());
    }
    // Check STK Push payment status
    public function stkPushQuery(Request $request){
        $validated = $request->validate([
            'checkout_request_id' => 'required|string|max:255'
        ]);

        $payment = Payment::where(
            'checkout_request_id',
            $validated['checkout_request_id']
        )->first();

        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found.'
            ], 404);
        }

        $token = $this->getAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'Could not authenticate with Daraja.'
            ], 502);
        }

        $shortcode = config('services.mpesa.shortcode');
        $passkey = config('services.mpesa.passkey');
        $timestamp = now('Africa/Nairobi')->format('YmdHis');

        $password = base64_encode(
            $shortcode . $passkey . $timestamp
        );

        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->withToken($token)
                ->post(
                    config('services.mpesa.base_url') .
                    '/mpesa/stkpushquery/v1/query',
                    [
                        'BusinessShortCode' => $shortcode,
                        'Password' => $password,
                        'Timestamp' => $timestamp,
                        'CheckoutRequestID' => $validated['checkout_request_id']
                    ]
                );

        } catch (ConnectionException $e) {
            return response()->json([
                'message' => 'Could not connect to Daraja. Try again later.'
            ], 503);
        }

        $data = $response->json();

        if (!is_array($data)) {
            return response()->json([
                'message' => 'Daraja returned an invalid response.'
            ], 502);
        }

        if ($response->successful() && isset($data['ResultCode'])) {

            if ((string) $data['ResultCode'] === '0') {
                $status = 'Success';
            } else {
                $status = 'Failed';
            }

            $this->updatePaymentStatus($payment, $status);
        }

        return response()->json($data, $response->status());
    }
    // Receive STK Push callback from Safaricom
    public function callback(Request $request){
        Log::info('M-Pesa Callback Received:', $request->all());

        $callback = $request->input('Body.stkCallback');

        if (!is_array($callback)) {
            Log::error('M-Pesa callback structure is invalid.');

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Invalid callback structure'
            ]);
        }

        $checkoutRequestId = $callback['CheckoutRequestID'] ?? null;
        $resultCode = $callback['ResultCode'] ?? null;

        if (!$checkoutRequestId || $resultCode === null) {
            Log::error('Callback is missing required payment details.');

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Missing callback details'
            ]);
        }

        $payment = Payment::where(
            'checkout_request_id',
            $checkoutRequestId
        )->first();

        if (!$payment) {
            Log::error('Payment not found for callback.', [
                'checkout_request_id' => $checkoutRequestId
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Payment record not found'
            ]);
        }

        // Determine payment status
        if ((string) $resultCode === '0') {
            $status = 'Success';
        } else {
            $status = 'Failed';
        }

        Log::info('M-Pesa payment status update attempt.', [
            'payment_id' => $payment->id,
            'old_status' => $payment->status,
            'new_status' => $status,
            'result_code' => $resultCode,
            'checkout_request_id' => $checkoutRequestId
        ]);

        $this->updatePaymentStatus($payment, $status);

        $payment->refresh();

        Log::info('M-Pesa payment status after update.', [
            'payment_id' => $payment->id,
            'status' => $payment->status
        ]);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed'
        ]);
    }
    // Update payment status
    private function updatePaymentStatus(Payment $payment, string $newStatus){
        // Ensures no change of a successful payment back to failed
        if ($payment->status == 'Success') {
            return;
        }

        $payment->status = $newStatus;
        $payment->save();
    }
}