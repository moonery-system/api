<?php

namespace Database\Seeders;

use App\Models\DeliveryStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = [
            [
                'name' => 'pending',
                'label' => 'Delivery created, but delivery man is pending.',
            ],
            [
                'name' => 'attached',
                'label' => "Delivery man has selected the delivery, but doesn't picked up yet.",
            ],
            [
                'name' => 'picked_up',
                'label' => 'Delivery man picked up the delivery.',
            ],
            [
                'name' => 'in_transit',
                'label' => 'Delivery in transit.',
            ],
            [
                'name' => 'delivered',
                'label' => 'Delivery has been delivered.',
            ],
            [
                'name' => 'client_address_not_found',
                'label' => 'Client address not found.',
            ],
            [
                'name' => 'client_not_found',
                'label' => 'Client not found.',
            ],
            [
                'name' => 'canceled_by_client',
                'label' => 'Delivery canceled by the client.',
            ],
            [
                'name' => 'canceled_by_admin',
                'label' => 'Delivery canceled by the admin.',
            ],
            [
                'name' => 'return_to_sender',
                'label' => 'Delivery has been returned to sender.',
            ],
        ];

        foreach ($statuses as $status) {
            DeliveryStatus::create($status);
        }
    }
}
