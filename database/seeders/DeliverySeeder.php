<?php

namespace Database\Seeders;

use App\Models\ClientAddress;
use App\Models\Delivery;
use App\Models\DeliveryItems;
use App\Models\DeliveryStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = User::where('email', 'admin@gmail.com')->first();
        $client = User::where('email', 'client@gmail.com')->first();
        $pending = DeliveryStatus::where('name', 'pending')->first();

        if (!$admin || !$client || !$pending) return;

        $address = ClientAddress::create([
            'user_id' => $client->id,
            'address_line' => 'Rua das Palmeiras, 128',
            'neighborhood' => 'Centro',
            'city' => 'Belo Horizonte',
            'state' => 'MG',
            'zip_code' => '30110-005',
            'complement' => 'Apto 302',
        ]);

        $deliveries = [
            [
                ['name' => 'caixa media', 'description' => 'Caixa de papelao reforcada.', 'quantity' => 2, 'weight' => 3.50],
                ['name' => 'envelope lacrado', 'description' => null, 'quantity' => 1, 'weight' => 0.20],
            ],
            [
                ['name' => 'monitor 24 polegadas', 'description' => 'Fragil, manter em pe.', 'quantity' => 1, 'weight' => 5.80],
            ],
        ];

        foreach ($deliveries as $index => $items) {
            $delivery = Delivery::create([
                'tracking_code' => 'MNY-' . Carbon::now()->year . '-SEED' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'creator_id' => $admin->id,
                'client_id' => $client->id,
                'client_address_id' => $address->id,
                'delivery_status_id' => $pending->id,
            ]);

            foreach ($items as $item) {
                DeliveryItems::create([
                    'delivery_id' => $delivery->id,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'weight' => $item['weight'],
                ]);
            }
        }
    }
}
