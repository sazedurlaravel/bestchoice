<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    // ---- Homepage ----

    public function test_homepage_loads_and_active_featured_products_display(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $this->get('/')->assertOk()->assertSee($product->name);
    }

    public function test_homepage_does_not_show_inactive_products(): void
    {
        $category = Category::factory()->create();
        $inactive = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'অদৃশ্য পণ্য',
            'is_active' => false,
            'is_featured' => true,
        ]);

        $this->get('/')->assertOk()->assertDontSee($inactive->name);
    }

    public function test_guest_homepage_account_navigation_points_to_live_login(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('login').'"', false);
    }

    public function test_authenticated_homepage_account_navigation_points_to_live_account(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/')->assertOk()->assertSee('href="'.route('account').'"', false);
    }

    public function test_homepage_shows_the_session_cart_count_in_shared_navigation(): void
    {
        $this->withSession(['cart' => ['item' => ['qty' => 2]]])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<span class="mobile-cart-count">2</span>', false)
            ->assertSee('<i class="fa-solid fa-cart-shopping"></i> কার্ট <span>2</span>', false);
    }

    // ---- Category page ----

    public function test_category_page_loads_and_shows_active_products(): void
    {
        $category = Category::factory()->create(['slug' => 'test-cat', 'is_active' => true]);
        $visible = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'ক্যাটাগরি পণ্য',
            'is_active' => true,
        ]);
        $hidden = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'বন্ধ পণ্য',
            'is_active' => false,
        ]);

        $response = $this->get(route('categories.show', $category));

        $response->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_inactive_category_returns_404(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $this->get(route('categories.show', $category))->assertNotFound();
    }

    public function test_category_page_uses_slug_routing(): void
    {
        $category = Category::factory()->create(['slug' => 'my-unique-category', 'is_active' => true]);

        $this->get('/category/my-unique-category')->assertOk();
    }

    // ---- Product detail ----

    public function test_active_product_detail_page_loads(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'ডিটেইল টেস্ট পণ্য',
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_product_detail_matches_reference_structure_without_rating_and_shows_related_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $related = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'name' => 'সম্পর্কিত পণ্য']);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('detail-premium', false)
            ->assertSee('detail-benefits', false)
            ->assertSee('পণ্যের তথ্য')
            ->assertDontSee('ক্রেতাদের পছন্দ')
            ->assertSee($related->name)
            ->assertSee('action="'.route('wishlist.toggle').'"', false);
    }

    public function test_product_detail_uses_database_description(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'short_description' => 'লাইভ পণ্যের বিবরণ',
            'full_description' => null,
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('লাইভ পণ্যের বিবরণ');
    }

    public function test_inactive_product_detail_page_returns_404(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->get(route('products.show', $product))->assertNotFound();
    }

    // ---- Search ----

    public function test_search_returns_matching_active_products(): void
    {
        $category = Category::factory()->create();
        $visible = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'দৃশ্যমান পণ্য',
            'is_active' => true,
        ]);
        $hidden = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'গোপন পণ্য',
            'is_active' => false,
        ]);

        $this->get('/search?q='.urlencode('দৃশ্যমান'))
            ->assertOk()
            ->assertSee($visible->name)
            ->assertDontSee($hidden->name);
    }

    public function test_search_page_loads_without_query(): void
    {
        $this->get('/search')->assertOk();
    }

    public function test_shop_is_a_separate_live_page(): void
    {
        $this->get(route('shop'))->assertOk()->assertSee('সব পণ্য');
    }

    // ---- Admin access control ----

    public function test_admin_routes_redirect_guests_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_admin_routes_deny_customer_role_with_403(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_admin_dashboard_accessible_to_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    // ---- Authentication ----

    public function test_login_page_loads(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('নতুন অ্যাকাউন্ট')
            ->assertSee(route('register.store'), false);
    }

    public function test_authenticated_customer_visiting_login_is_sent_to_live_account(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('login'))->assertRedirect(route('account'));
    }

    public function test_login_with_valid_credentials_redirects_to_admin(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $this->post('/login', ['email' => 'admin@test.com', 'password' => 'secret123'])
            ->assertRedirect('/admin');
    }

    public function test_customer_can_login_with_mobile_number(): void
    {
        $customer = User::factory()->create([
            'mobile' => '01700000000',
            'password' => bcrypt('secret123'),
            'role' => 'customer',
        ]);

        $this->post(route('login.store'), ['email' => $customer->mobile, 'password' => 'secret123'])
            ->assertRedirect(route('account'));
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_logout_invalidates_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_customer_can_register_and_is_logged_in(): void
    {
        $this->post(route('register.store'), [
            'name' => 'নতুন ক্রেতা',
            'email' => 'customer@test.com',
            'mobile' => '01700000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('account'));

        $this->assertAuthenticatedAs(User::where('email', 'customer@test.com')->first());
        $this->assertDatabaseHas('users', ['email' => 'customer@test.com', 'role' => 'customer']);
    }

    public function test_authenticated_customer_can_view_account(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('account'))
            ->assertOk()
            ->assertSee('আমার অ্যাকাউন্ট')
            ->assertSee(route('shop'), false);
    }

    public function test_account_shows_only_the_authenticated_customers_orders(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $customerOrder = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'BC-LIVE-001',
            'customer_name' => $customer->name,
            'customer_phone' => '01700000000',
            'customer_address' => 'ঢাকা',
            'subtotal' => 500,
            'shipping_charge' => 60,
            'total' => 560,
        ]);
        Order::create([
            'user_id' => $otherCustomer->id,
            'order_number' => 'BC-OTHER-001',
            'customer_name' => $otherCustomer->name,
            'customer_phone' => '01800000000',
            'customer_address' => 'চট্টগ্রাম',
            'subtotal' => 800,
            'shipping_charge' => 60,
            'total' => 860,
        ]);

        $this->actingAs($customer)->get(route('account'))
            ->assertOk()
            ->assertSee($customerOrder->order_number)
            ->assertDontSee('BC-OTHER-001');
    }

    public function test_guest_is_redirected_to_login_from_account(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
    }

    public function test_customer_can_update_profile(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->patch(route('account.profile.update'), [
            'name' => 'আপডেটেড নাম',
            'email' => 'updated@test.com',
            'mobile' => '01800000000',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'name' => 'আপডেটেড নাম', 'email' => 'updated@test.com']);
    }

    // ---- Product model validation ----

    public function test_invalid_product_pricing_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Product::factory()->create(['regular_price' => 100, 'selling_price' => 120]);
    }

    public function test_product_has_discount_when_selling_price_is_lower(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'regular_price' => 1000,
            'selling_price' => 800,
        ]);

        $this->assertTrue($product->has_discount);
    }

    public function test_product_has_no_discount_when_prices_are_equal(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'regular_price' => 500,
            'selling_price' => 500,
        ]);

        $this->assertFalse($product->has_discount);
    }

    public function test_wishlist_uses_session_and_active_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->post('/wishlist/toggle', ['product_id' => $product->id])->assertRedirect();
        $this->get('/wishlist')->assertOk()->assertSee($product->name);
    }

    public function test_wishlist_toggle_removes_an_existing_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withSession(['wishlist' => [$product->id]])
            ->post(route('wishlist.toggle'), ['product_id' => $product->id])
            ->assertSessionHas('wishlist', []);
    }

    public function test_wishlist_toggle_returns_to_the_item_anchor(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withSession(['wishlist' => [$product->id]])
            ->post(route('wishlist.toggle'), ['product_id' => $product->id, 'return_to' => 'wishlist'])
            ->assertRedirect(route('wishlist').'#wishlist-item-'.$product->id);
    }

    public function test_wishlist_toggle_returns_json_for_smooth_updates(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withHeader('Accept', 'application/json')
            ->postJson(route('wishlist.toggle'), ['product_id' => $product->id])
            ->assertOk()
            ->assertJson(['saved' => true, 'count' => 1]);
    }

    public function test_wishlist_page_has_a_remove_form_for_each_saved_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withSession(['wishlist' => [$product->id]])
            ->get(route('wishlist'))
            ->assertOk()
            ->assertSee('পছন্দের তালিকা থেকে সরান')
            ->assertSee('name="return_to" value="wishlist"', false);
    }

    public function test_homepage_bootstraps_wishlist_from_the_session(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $this->withSession(['wishlist' => [$product->id]])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('wish-button is-saved', false);
    }
}
