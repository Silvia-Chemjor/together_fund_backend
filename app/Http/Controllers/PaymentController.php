<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function readAll(){
        try{
            $payments = Payment::all();
            return response()->json([
                'payments'=>$payments
            ],200);
        }catch(\Exception $e){
        return response()->json([
            'message'=>'Failed to fetch.',
            'error'=>$e->getMessage()
        ],500);

        }
    }
     public function read($id){
        try{
            $payment = Payment::where('id',$id) ->first();
            return response()->json([
                'payment'=>$payment
            ],200);
        }catch(\Exception $e){
        return response()->json([
            'message'=>'Failed to fetch.',
            'error'=>$e->getMessage()
        ],500);
        }

    }
    public function deletePayment($id){
        try{
            $payment =Payment::where('id',$id)->first();
            if($payment){
                $payment -> delete();
                return response()->json("Payment delete successfully.");
            }
            else{
                return response()->json("Payment not found");
            }
        }catch(\Exception $e){
        return response()->json([
            'message'=>'Failed to delete',
            'error'=>$e->getMessage()
        ],500);
        }
    }
    public function myContributions(Request $request){
        try {
            $payments = Payment::with('group')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Contributions fetched successfully.',
                'contributions' => $payments
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch contributions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
