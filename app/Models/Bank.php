<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bank extends Model
{
    use HasFactory;

    protected $table = 'wlt_banks';

    protected $fillable = [
        'key',
        'bank_name',
        'bank_code',
        'bank_logo',
        'swift_code',
        'country_code',
        'bank_type',
        'address',
        'phone',
        'email',
        'website',
        'is_active',
        'supports_transfers',
        'supports_deposits',
        'supports_withdrawals',
        'min_balance',
        'max_balance',
        'daily_transfer_limit',
        'supported_currencies',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_transfers' => 'boolean',
        'supports_deposits' => 'boolean',
        'supports_withdrawals' => 'boolean',
        'min_balance' => 'decimal:2',
        'max_balance' => 'decimal:2',
        'daily_transfer_limit' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bank) {
            if (empty($bank->key)) {
                $bank->key = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'key';
    }
}