<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Payloads\Auth\RegisterStorePayload;
use App\Models\Store;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final readonly class RegisterStore
{
    /**
     * @return array{user: User, store: Store, token: string}
     */
    public function handle(RegisterStorePayload $payload, ?UploadedFile $image = null): array
    {
        $user = null;
        $store = null;

        DB::transaction(function () use ($payload, $image, &$user, &$store) {
            $userData = $payload->toUserArray();
            $userData['password'] = Hash::make($userData['password']);

            $user = User::create($userData);
            Wallet::create(['user_id' => $user->id, 'balance' => 0]);

            $storeData = $payload->toStoreArray($user->id);

            if ($image) {
                $path = $image->store('stores', 'public');
                $storeData['image'] = url('storage/' . $path);
            }

            $store = Store::create($storeData);

            DB::statement(
                "UPDATE stores SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = NOW() WHERE id = ?",
                [$payload->lng, $payload->lat, $store->id]
            );
        });

        $token = auth('api')->login($user);

        return ['user' => $user, 'store' => $store, 'token' => $token];
    }
}
