<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance'];
    protected $casts    = ['balance' => 'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function credit(float $amount, string $type = 'top_up', string $description = ''): void
    {
        $this->increment('balance', $amount);
        $this->transactions()->create(['amount' => $amount, 'type' => $type, 'description' => $description]);
    }

    public function debit(float $amount, string $type = 'payment', string $description = ''): void
    {
        $this->decrement('balance', $amount);
        $this->transactions()->create(['amount' => $amount, 'type' => $type, 'description' => $description]);
    }
}
