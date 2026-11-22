<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Contracts\View\View;

class InformationController extends Controller
{
    public function about(): View
    {
        return view('storefront.about', $this->storeData());
    }

    public function contact(): View
    {
        return view('storefront.contact', $this->storeData());
    }

    public function faq(): View
    {
        return view('storefront.faq', $this->storeData());
    }

    public function policy(): View
    {
        return view('storefront.policy', $this->storeData());
    }

    /** @return array{storeName:string,storeSettings:array<string,mixed>} */
    private function storeData(): array
    {
        $storeSettings = StoreSetting::query()->pluck('value', 'key')->all();

        return [
            'storeSettings' => $storeSettings,
            'storeName' => $storeSettings['store_name'] ?? 'Best Choice Online Shopping',
        ];
    }
}
