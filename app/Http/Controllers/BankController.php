<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Artifacts\CreateController;
use App\Http\Controllers\Artifacts\ReadController;
use App\Http\Controllers\Artifacts\UpdateController;
use App\Http\Controllers\Artifacts\DeleteController;
use Inertia\Inertia;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $banks = $this->getBanks();

            return $banks; 
    }

    public function getBanks()
    {
        try {
            $readController = new ReadController();
            $banks = $readController->GetAllRows('wlt_banks', 1000);

            return  $banks;
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve banks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:10|unique:wlt_banks,bank_code',
            'swift_code' => 'required|string|min:8|max:11|unique:wlt_banks,swift_code',
            'country_code' => 'required|string|max:3',
            'bank_type' => 'required|in:commercial,islamic',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'required|url|max:255',
            'min_balance' => 'required|numeric|min:0',
            'max_balance' => 'required|numeric|gt:min_balance',
            'daily_transfer_limit' => 'required|numeric|min:1',
            'supported_currencies' => 'required|string',
            'notes' => 'nullable|string|max:1000'
        ], [
            'bank_code.unique' => 'This bank code is already in use.',
            'swift_code.unique' => 'This SWIFT code is already in use.',
            'swift_code.min' => 'SWIFT code must be at least 8 characters.',
            'swift_code.max' => 'SWIFT code must not exceed 11 characters.',
            'max_balance.gt' => 'Maximum balance must be greater than minimum balance.',
        ]);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator->errors());
        }

        try {
            $createController = new CreateController();
            
            $bankData = [
                'bank_name' => $request->bank_name,
                'bank_code' => strtoupper($request->bank_code),
                'swift_code' => strtoupper($request->swift_code),
                'country_code' => $request->country_code,
                'bank_type' => $request->bank_type,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'is_active' => $request->boolean('is_active', true),
                'supports_transfers' => $request->boolean('supports_transfers', true),
                'supports_deposits' => $request->boolean('supports_deposits', true),
                'supports_withdrawals' => $request->boolean('supports_withdrawals', true),
                'min_balance' => $request->min_balance,
                'max_balance' => $request->max_balance,
                'daily_transfer_limit' => $request->daily_transfer_limit,
                'supported_currencies' => $request->supported_currencies,
                'notes' => $request->notes,
            ];

            $bankKey = $createController->CreateSingleRow('wlt_banks', $bankData);

            return Redirect::back()->with('success', 'Bank created successfully');

        } catch (\Exception $e) {
            return Redirect::back()->withErrors(['error' => 'Failed to create bank: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Bank $bank)
    {
        try {
            $readController = new ReadController();
            $bankData = $readController->GetSingleRow('wlt_banks', $bank->key);
            
            if (!$bankData) {
                return response()->json(['error' => 'Bank not found'], 404);
            }
            
            return response()->json($bankData);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve bank: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bank $bank)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:10|unique:wlt_banks,bank_code,' . $bank->id,
            'swift_code' => 'required|string|min:8|max:11|unique:wlt_banks,swift_code,' . $bank->id,
            'country_code' => 'required|string|max:3',
            'bank_type' => 'required|in:commercial,islamic',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'website' => 'required|url|max:255',
            'min_balance' => 'required|numeric|min:0',
            'max_balance' => 'required|numeric|gt:min_balance',
            'daily_transfer_limit' => 'required|numeric|min:1',
            'supported_currencies' => 'required|string',
            'notes' => 'nullable|string|max:1000'
        ], [
            'bank_code.unique' => 'This bank code is already in use.',
            'swift_code.unique' => 'This SWIFT code is already in use.',
            'swift_code.min' => 'SWIFT code must be at least 8 characters.',
            'swift_code.max' => 'SWIFT code must not exceed 11 characters.',
            'max_balance.gt' => 'Maximum balance must be greater than minimum balance.',
        ]);

        if ($validator->fails()) {
            return Redirect::back()->withErrors($validator->errors());
        }

        try {
            $updateController = new UpdateController();
            
            $updateData = [
                'bank_name' => $request->bank_name,
                'bank_code' => strtoupper($request->bank_code),
                'swift_code' => strtoupper($request->swift_code),
                'country_code' => $request->country_code,
                'bank_type' => $request->bank_type,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'website' => $request->website,
                'is_active' => $request->boolean('is_active', true),
                'supports_transfers' => $request->boolean('supports_transfers', true),
                'supports_deposits' => $request->boolean('supports_deposits', true),
                'supports_withdrawals' => $request->boolean('supports_withdrawals', true),
                'min_balance' => $request->min_balance,
                'max_balance' => $request->max_balance,
                'daily_transfer_limit' => $request->daily_transfer_limit,
                'supported_currencies' => $request->supported_currencies,
                'notes' => $request->notes,
            ];

            $result = $updateController->UpdateSingleRow('wlt_banks', $bank->key, $updateData);

            if ($result['updated']) {
                return Redirect::back()->with('success', 'Bank updated successfully');
            } else {
                return Redirect::back()->with('info', 'No changes were made to the bank');
            }

        } catch (\Exception $e) {
            return Redirect::back()->withErrors(['error' => 'Failed to update bank: ' . $e->getMessage()]);
        }
    }

    /**
     * Get banks for wallet creation (returns Inertia response)
     */
    public function getBanksForWallets()
    {
        try {
            $readController = new ReadController();
            $banksData = $readController->GetAllRows('wlt_banks', 1000);
            
            // Filter and format banks for dropdown
            $banks = collect($banksData)->filter(function($bank) {
                return $bank['is_active'] == 1; // Only active banks
            })->map(function($bank) {
                return [
                    'key' => $bank['key'],
                    'bank_name' => $bank['bank_name'],
                    'bank_code' => $bank['bank_code'],
                    'swift_code' => $bank['swift_code'],
                    'supported_currencies' => json_decode($bank['supported_currencies'] ?? '["AED"]', true)
                ];
            })->values()->all();

            // Return current page with banks data
            return Inertia::render('wallets/MyWalletsList', [
                'banks' => $banks
            ]);
            
        } catch (\Exception $e) {
            // Return error response but still with Inertia
            return Inertia::render('wallets/MyWalletsList', [
                'banks' => [],
                'error' => 'Failed to load banks: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bank $bank)
    {
        try {
            $deleteController = new DeleteController();
            $deleted = $deleteController->DeleteRow('wlt_banks', $bank->key);
            
            if ($deleted) {
                return response()->json([
                    'message' => 'Bank deleted successfully'
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to delete bank'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete bank',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}