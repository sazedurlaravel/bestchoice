<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        if (Order::exists()) {
            return;
        }

        $products = Product::active()->take(10)->get();

        if ($products->isEmpty()) {
            return;
        }

        $customers = [
            ['নাজমুল হোসেন', '01712345678', 'বাড়ি ৩, রোড ৭, ধানমন্ডি', 'ঢাকা'],
            ['রাহেলা বেগম', '01812345679', 'ফ্ল্যাট ৪বি, পাথরঘাটা', 'চট্টগ্রাম'],
            ['করিম উদ্দিন', '01912345680', 'বাড়ি ১২, সাহেবপাড়া', 'রাজশাহী'],
            ['সুমাইয়া আক্তার', '01612345681', 'রোড ৩, গুলশান ২', 'ঢাকা'],
            ['মোঃ রফিকুল ইসলাম', '01512345682', 'পল্লবী, মিরপুর', 'ঢাকা'],
        ];

        $statuses = ['pending', 'pending', 'processing', 'shipped', 'delivered'];

        foreach ($customers as $i => [$name, $phone, $address, $district]) {
            $items = $products->random(rand(1, 3));
            $subtotal = 0.0;

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_address' => $address,
                'customer_district' => $district,
                'subtotal' => 0,
                'shipping_charge' => 60,
                'total' => 0,
                'status' => $statuses[$i],
                'payment_method' => 'cash_on_delivery',
            ]);

            foreach ($items as $product) {
                $qty = rand(1, 3);
                $price = (float) $product->selling_price;
                $itemSubtotal = $price * $qty;
                $subtotal += $itemSubtotal;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $itemSubtotal,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal + 60]);
        }
    }
}
