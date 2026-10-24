<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignMembershipTest extends TestCase
{
    use DatabaseTransactions;

    private function campaign(): Campaign
    {
        return Campaign::query()->create([
            'title' => 'P01 Test Campaign',
            'slug' => 'p01-campaign-' . Str::random(8),
            'route_type' => Campaign::ROUTE_STANDARD,
            'status' => true,
        ]);
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'P01 Test Category',
            'slug' => 'p01-category-' . Str::random(8),
            'status' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'P01 Test Product',
            'slug' => 'p01-product-' . Str::random(8),
            'product_code' => 'P01-' . strtoupper(Str::random(10)),
            'new_price' => 350,
            'stock' => 50,
            'status' => true,
            'is_free_delivery' => false,
        ]);
    }

    private function payload(array $products): array
    {
        return [
            'customer_name' => 'Fixture Buyer',
            'phone' => '01712345678',
            'address' => 'Synthetic test address',
            'delivery_area' => null,
            'order_form_token' => 'p01-test-one-time-token',
            'products' => $products,
        ];
    }

    public function test_foreign_campaign_product_is_rejected_without_writing_customers_or_orders(): void
    {
        $campaign = $this->campaign();
        $foreign = $this->product();

        $this->withSession(['campaign_order_form_tokens' => ['p01-test-one-time-token']]);

        $this->postJson(route('campaign.order.store', $campaign->slug),
            $this->payload([['id' => $foreign->id, 'quantity' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('products');

        $this->assertDatabaseMissing('orders', ['campaign_id' => $campaign->id]);
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, Customer::query()->where('phone', '01712345678')->count());
        $this->assertContains('p01-test-one-time-token', session('campaign_order_form_tokens'));
    }

    public function test_mixed_attached_and_foreign_lines_are_rejected_atomically(): void
    {
        $campaign = $this->campaign();
        $allowed = $this->product();
        $foreign = $this->product();
        $campaign->products()->attach($allowed->id, ['campaign_price' => 295]);

        $this->withSession(['campaign_order_form_tokens' => ['p01-test-one-time-token']]);

        $this->postJson(route('campaign.order.store', $campaign->slug),
            $this->payload([
                ['id' => $allowed->id, 'quantity' => 1],
                ['id' => $foreign->id, 'quantity' => 1],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('products');

        $this->assertSame(0, Order::query()->where('campaign_id', $campaign->id)->count());
        $this->assertSame(0, Customer::query()->where('phone', '01712345678')->count());
    }

    public function test_invalid_shipping_does_not_create_customer_or_consume_form_token(): void
    {
        $campaign = $this->campaign();
        $product = $this->product();
        $campaign->products()->attach($product->id, ['campaign_price' => 295]);

        $this->withSession(['campaign_order_form_tokens' => ['p01-test-one-time-token']]);

        $this->postJson(route('campaign.order.store', $campaign->slug),
            $this->payload([['id' => $product->id, 'quantity' => 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_area');

        $this->assertSame(0, Customer::query()->where('phone', '01712345678')->count());
        $this->assertContains('p01-test-one-time-token', session('campaign_order_form_tokens'));
    }
}
