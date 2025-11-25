// Add this to SettingsController index method to include banks data
$banks = \App\Models\Bank::orderBy('bank_name')->get();

// In the Inertia::render call, add:
// 'banks' => $banks,