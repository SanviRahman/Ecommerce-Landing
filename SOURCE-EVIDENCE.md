# Source Evidence - Supplied Snapshot

Evidence is from the uploaded source archives, read statically. Line numbers are 1-based. A snippet is not a production test. Secrets and live customer data are excluded.

Target SHA-256: `349b43d5ad81f83480712c6fd618a4d95c39ba368dbdec6eba291d9cbe190004`.

Reference SHA-256: `0f7f4a664be0f97dfe812db9dbac09b3c6af8a939b04a6e102bb02b12969556f`.

## E01 - Target manifests

`TARGET:composer.json`

Lines 8-28:

```text
8:     "require": {
9:         "php": "^8.3",
10:         "barryvdh/laravel-dompdf": "^3.1",
11:         "guzzlehttp/guzzle": "^7.10",
12:         "jeroennoten/laravel-adminlte": "^3.16",
13:         "laravel/framework": "^13.0",
14:         "laravel/tinker": "^3.0",
15:         "laravel/ui": "^4.6",
16:         "spatie/laravel-medialibrary": "^11.22",
17:         "spatie/laravel-permission": "^7.4"
18:     },
19:     "require-dev": {
20:         "fakerphp/faker": "^1.23",
21:         "laravel/pail": "^1.2.5",
22:         "laravel/pao": "^1.0.6",
23:         "laravel/pint": "^1.27",
24:         "mockery/mockery": "^1.6",
25:         "nunomaduro/collision": "^8.6",
26:         "phpunit/phpunit": "^12.5.12"
27:     },
28:     "autoload": {
```

## E02 - Homepage resolves a campaign and shared view

`TARGET:app/Http/Controllers/Frontend/HomeController.php`

Lines 27-48:

```text
27:         /**
28:          * Homepage campaign resolving rule:
29:          * 1. Show the active campaign marked as default.
30:          * 2. If no default campaign is selected, fallback to the latest active campaign.
31:          * 3. If every campaign is inactive, render an empty homepage while keeping
32:          *    the physical header shell visible and hiding all header content.
33:          */
34:         $campaign = Campaign::resolveHomepageCampaign([
35:             'categories' => function ($query) {
36:                 $query->where('categories.status', true)
37:                     ->orderByPivot('sort_order');
38:             },
39:             'brands' => function ($query) {
40:                 $query->where('brands.status', true)
41:                     ->orderByPivot('sort_order');
42:             },
43:             'products' => function ($query) {
44:                 $query->with(['category', 'brand'])
45:                     ->where('products.status', true)
46:                     ->orderByPivot('sort_order');
47:             },
48:         ]);
```

Lines 155-165:

```text
155:     /**
156:      * Prevent browser/proxy cache from preserving an old campaign state after
157:      * an administrator changes campaign status.
158:      */
159:     private function homeResponse(array $data): Response
160:     {
161:         return response()
162:             ->view('frontend.pages.home', $data)
163:             ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
164:             ->header('Pragma', 'no-cache')
165:             ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
```

## E03 - Campaign page uses the same view

`TARGET:app/Http/Controllers/Admin/CampaignPageController.php`

Lines 16-40:

```text
16:     public function show(Campaign $campaign)
17:     {
18:         abort_if($campaign->usesCustomRoute(), 404);
19: 
20:         return $this->renderCampaign($campaign);
21:     }
22: 
23:     public function showByCustomRoute(string $customRoute)
24:     {
25:         $campaign = Campaign::query()
26:             ->where('route_type', Campaign::ROUTE_CUSTOM)
27:             ->where('custom_route', strtolower($customRoute))
28:             ->firstOrFail();
29: 
30:         return $this->renderCampaign($campaign);
31:     }
32: 
33:     private function renderCampaign(Campaign $campaign)
34:     {
35:         abort_if(! $campaign->status, 404);
36: 
37:         $campaign->load([
38:             'categories' => function ($query) {
39:                 $query->where('categories.status', true)
40:                     ->orderByPivot('sort_order');
```

Lines 88-120:

```text
88:             ->latest()
89:             ->get();
90: 
91:         $shippingCharges = ShippingCharge::resolvedForCampaign($campaign->id);
92: 
93:         return response()
94:             ->view('frontend.pages.home', [
95:                 'siteSetting' => $siteSetting,
96:                 'campaign' => $campaign,
97:                 'categories' => $categories,
98:                 'brands' => $brands,
99:                 'products' => $products,
100:                 'orderProducts' => $orderProducts,
101:                 'reviews' => $reviews,
102:                 'faqs' => $faqs,
103:                 'socialMedias' => $socialMedias,
104:                 'shippingCharges' => $shippingCharges,
105:                 'courierServices' => config('couriers.list', []),
106:                 'orderFormToken' => $orderFormToken,
107:             ])
108:             ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
109:             ->header('Pragma', 'no-cache')
110:             ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
111:     }
112: 
113:     private function makeOrderFormToken(): string
114:     {
115:         $tokens = session()->get('campaign_order_form_tokens', []);
116: 
117:         if (! is_array($tokens)) {
118:             $tokens = [];
119:         }
120: 
```

## E04 - Existing product model and schema

`TARGET:app/Models/Product.php`

Lines 16-49:

```text
16:     protected $fillable = [
17:         'category_id',
18:         'brand_id',
19:         'name',
20:         'slug',
21:         'product_code',
22:         'purchase_price',
23:         'old_price',
24:         'new_price',
25:         'stock',
26:         'sold_quantity',
27:         'weight_size',
28:         'short_description',
29:         'full_description',
30:         'is_top_sale',
31:         'is_feature',
32:         'is_flash_sale',
33:         'is_free_delivery',
34:         'status',
35:         'meta_title',
36:         'meta_description',
37:     ];
38: 
39:     protected $casts = [
40:         'purchase_price'   => 'integer',
41:         'old_price'        => 'integer',
42:         'new_price'        => 'integer',
43:         'stock'            => 'integer',
44:         'sold_quantity'    => 'integer',
45:         'is_top_sale'      => 'boolean',
46:         'is_feature'       => 'boolean',
47:         'is_flash_sale'    => 'boolean',
48:         'is_free_delivery' => 'boolean',
49:         'status'           => 'boolean',
```

Lines 59-87:

```text
59:     public function registerMediaCollections(): void
60:     {
61:         $this->addMediaCollection('product_thumbnail')
62:             ->useDisk('public')
63:             ->singleFile();
64: 
65:         $this->addMediaCollection('product_gallery')
66:             ->useDisk('public');
67:     }
68: 
69:     public function category()
70:     {
71:         return $this->belongsTo(Category::class);
72:     }
73: 
74:     public function brand()
75:     {
76:         return $this->belongsTo(Brand::class);
77:     }
78: 
79:     public function campaigns()
80:     {
81:         return $this->belongsToMany(Campaign::class, 'campaign_product')
82:             ->withPivot(['campaign_price', 'sort_order', 'is_default'])
83:             ->withTimestamps();
84:     }
85: 
86:     public function orderItems()
87:     {
```

Lines 155-159:

```text
155:     public function isInStock(int $quantity = 1): bool
156:     {
157:         return $this->stock >= $quantity;
158:     }
159: }
```

## E05 - Flat category baseline

`TARGET:database/migrations/2026_05_03_050716_create_categories_table.php`

Lines 12-25:

```text
12:     public function up(): void
13:     {
14:         Schema::create('categories', function (Blueprint $table) {
15:             $table->id();
16:             $table->string('name');
17:             $table->string('slug')->unique();
18:             $table->boolean('is_front_view')->default(false);
19:             $table->boolean('status')->default(true);
20:             $table->timestamps();
21:             $table->softDeletes();
22:         });
23:     }
24: 
25:     /**
```

## E06 - Campaign membership fallback

`TARGET:app/Http/Controllers/Admin/CampaignOrderController.php`

Lines 203-215:

```text
203:         $request->validate([
204:             'customer_name'       => ['required', 'string', 'max:255'],
205:             'phone'               => ['required', 'string', 'regex:/^01[0-9]{9}$/'],
206:             'address'             => ['required', 'string'],
207:             'delivery_area'       => ['nullable', 'string', 'max:255'],
208:             'customer_note'       => ['nullable', 'string', 'max:1000'],
209:             'order_form_token'    => ['required', 'string', 'max:100'],
210:             'device_identifier'   => ['nullable', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
211: 
212:             'products'            => ['required', 'array', 'min:1'],
213:             'products.*.id'       => ['required', 'integer', 'exists:products,id'],
214:             'products.*.quantity' => ['required', 'integer', 'min:1'],
215:         ]);
```

Lines 243-297:

```text
243:             $campaign->load([
244:                 'products' => function ($query) {
245:                     $query->where('products.status', true);
246:                 },
247:             ]);
248: 
249:             $campaignProducts = $campaign->products->keyBy('id');
250: 
251:             $submittedProducts = collect($request->products)
252:                 ->groupBy('id')
253:                 ->map(function ($items) {
254:                     return [
255:                         'id'       => (int) $items->first()['id'],
256:                         'quantity' => (int) $items->sum('quantity'),
257:                     ];
258:                 })
259:                 ->values();
260: 
261:             $subTotal = 0;
262:             $orderItems = [];
263: 
264:             foreach ($submittedProducts as $submittedProduct) {
265:                 $productId = (int) $submittedProduct['id'];
266:                 $product = $campaignProducts->get($productId);
267: 
268:                 if (! $product) {
269:                     $product = Product::query()
270:                         ->where('status', true)
271:                         ->whereKey($productId)
272:                         ->first();
273:                 }
274: 
275:                 if (! $product) {
276:                     continue;
277:                 }
278: 
279:                 $quantity = max(1, (int) $submittedProduct['quantity']);
280:                 $campaignPrice = isset($product->pivot) ? (int) ($product->pivot->campaign_price ?? 0) : 0;
281:                 $unitPrice = $campaignPrice > 0 ? $campaignPrice : (int) $product->new_price;
282: 
283:                 if ($unitPrice <= 0) {
284:                     continue;
285:                 }
286: 
287:                 $lineTotal = $unitPrice * $quantity;
288:                 $subTotal += $lineTotal;
289: 
290:                 $orderItems[] = [
291:                     'product'          => $product,
292:                     'quantity'         => $quantity,
293:                     'unit_price'       => $unitPrice,
294:                     'line_total'       => $lineTotal,
295:                     'is_free_delivery' => (bool) ($product->is_free_delivery ?? false),
296:                 ];
297:             }
```

## E07 - Campaign order transaction and items

`TARGET:app/Http/Controllers/Admin/CampaignOrderController.php`

Lines 231-247:

```text
231:         if (! $this->consumeOrderFormToken($request->input('order_form_token'))) {
232:             return back()
233:                 ->withInput($request->except(['products']))
234:                 ->with('error', 'এই পুরোনো ফর্ম দিয়ে আবার অর্ডার করা যাবে না। অনুগ্রহ করে পেজ refresh করে নতুন করে অর্ডার করুন।');
235:         }
236: 
237:         return DB::transaction(function () use ($request, $campaign, $customerIdentityService) {
238:             $customer = $customerIdentityService->resolveOrCreate(
239:                 $request->customer_name,
240:                 $request->phone,
241:                 'phone'
242:             );
243:             $campaign->load([
244:                 'products' => function ($query) {
245:                     $query->where('products.status', true);
246:                 },
247:             ]);
```

Lines 299-327:

```text
299:             if (empty($orderItems)) {
300:                 return back()
301:                     ->withInput()
302:                     ->with('error', 'Please select at least one valid product.');
303:             }
304: 
305:             $hasAnyFreeDeliveryProduct = collect($orderItems)
306:                 ->contains(fn ($item) => (bool) $item['is_free_delivery']);
307: 
308:             $selectedShippingCharge = null;
309: 
310:             if (! $hasAnyFreeDeliveryProduct) {
311:                 $availableShippingCharges = ShippingCharge::resolvedForCampaign($campaign->id);
312: 
313:                 $selectedShippingCharge = $availableShippingCharges
314:                     ->first(fn (ShippingCharge $charge) => (string) $charge->id === (string) $request->delivery_area);
315: 
316:                 if (! $selectedShippingCharge) {
317:                     return back()
318:                         ->withInput()
319:                         ->with('error', 'Please select a valid delivery area.');
320:                 }
321: 
322:                 $shippingCharge = (int) $selectedShippingCharge->delivery_charge;
323:                 $deliveryAreaName = $selectedShippingCharge->area_name;
324:             } else {
325:                 $shippingCharge = 0;
326:                 $deliveryAreaName = 'free_delivery';
327:             }
```

Lines 373-408:

```text
373:             foreach ($orderItems as $item) {
374:                 OrderItem::create([
375:                     'order_id'     => $order->id,
376:                     'product_id'   => $item['product']->id,
377:                     'product_name' => $item['product']->name,
378:                     'unit_price'   => $item['unit_price'],
379:                     'quantity'     => $item['quantity'],
380:                     'total_price'  => $item['line_total'],
381:                 ]);
382:             }
383: 
384:             app(OrderAssignmentService::class)->assign($order);
385: 
386:             return redirect()
387:                 ->route('order.success', $order->success_token)
388:                 ->with('success', 'আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে। খুব শীঘ্রই আমাদের প্রতিনিধি যোগাযোগ করবে।');
389:         });
390:     }
391: 
392:     private function consumeOrderFormToken(?string $token): bool
393:     {
394:         if (! $token) {
395:             return false;
396:         }
397: 
398:         $tokens = session()->get('campaign_order_form_tokens', []);
399: 
400:         if (! is_array($tokens) || ! in_array($token, $tokens, true)) {
401:             return false;
402:         }
403: 
404:         $tokens = array_values(array_filter($tokens, fn ($storedToken) => $storedToken !== $token));
405: 
406:         session()->put('campaign_order_form_tokens', $tokens);
407: 
408:         return true;
```

## E08 - Separate service inventory logic

`TARGET:app/Services/OrderManagementService.php`

Lines 22-43:

```text
22:     public function createOrder(array $data, Request $request): Order
23:     {
24:         return DB::transaction(function () use ($data, $request) {
25:             $phone = $this->customerIdentityService->normalizePhone($data['phone'] ?? null);
26:             $customer = $this->customerIdentityService->resolveOrCreate(
27:                 (string) ($data['customer_name'] ?? ''),
28:                 $phone,
29:                 'phone'
30:             );
31: 
32:             $product = Product::where('status', true)->findOrFail($data['product_id']);
33: 
34:             $pricing = $this->pricingService->calculate(
35:                 $product,
36:                 (int) ($data['quantity'] ?? 1),
37:                 $data['shipping_charge_id'] ?? null
38:             );
39: 
40:             if (! $product->isInStock($pricing['quantity'])) {
41:                 throw new Exception('Product stock is not available.');
42:             }
43: 
```

Lines 101-125:

```text
101: 
102:             OrderItem::create([
103:                 'order_id'     => $order->id,
104:                 'product_id'   => $product->id,
105:                 'product_name' => $product->name,
106:                 'quantity'     => $pricing['quantity'],
107:                 'unit_price'   => $pricing['unit_price'],
108:                 'total_price'  => $pricing['sub_total'],
109:             ]);
110: 
111:             if ($fakeCheck['is_fake']) {
112:                 FakeOrderLog::create([
113:                     'order_id'     => $order->id,
114:                     'fake_reason'  => $fakeCheck['reason_text'],
115:                     'detected_by'  => 'system',
116:                 ]);
117:             } else {
118:                 $product->decrement('stock', $pricing['quantity']);
119:                 $product->increment('sold_quantity', $pricing['quantity']);
120: 
121:                 $this->orderAssignmentService->assign($order);
122:             }
123: 
124:             return $order->load('items', 'campaign', 'assignedEmployee');
125:         });
```

## E09 - Admin-authenticated GET mutations

`TARGET:routes/command.php`

Lines 58-83:

```text
58: Route::prefix('command')
59:     ->name('command.')
60:     ->middleware(['auth', 'role:admin', 'lte_context:admin'])
61:     ->group(function () use ($redirectWithToast) {
62:         Route::get('/clear-cache', function () use ($redirectWithToast) {
63:             Artisan::call('cache:clear');
64: 
65:             return $redirectWithToast('success', 'Cache cleared successfully.');
66:         })->name('clear-cache');
67: 
68:         Route::get('/clear-config', function () use ($redirectWithToast) {
69:             Artisan::call('config:clear');
70: 
71:             return $redirectWithToast('success', 'Config cleared successfully.');
72:         })->name('clear-config');
73: 
74:         Route::get('/clear-route', function () use ($redirectWithToast) {
75:             Artisan::call('route:clear');
76: 
77:             return $redirectWithToast('success', 'Route cache cleared successfully.');
78:         })->name('clear-route');
79: 
80:         Route::get('/clear-view', function () use ($redirectWithToast) {
81:             Artisan::call('view:clear');
82: 
83:             return $redirectWithToast('success', 'View cache cleared successfully.');
```

Lines 101-127:

```text
101:         Route::get('/optimize', function () use ($redirectWithToast) {
102:             Artisan::call('optimize');
103: 
104:             return $redirectWithToast('success', 'Application optimized successfully.');
105:         })->name('optimize');
106: 
107:         Route::get('/optimize-clear', function () use ($redirectWithToast) {
108:             Artisan::call('optimize:clear');
109: 
110:             return $redirectWithToast('success', 'Optimize cache cleared successfully.');
111:         })->name('optimize-clear');
112: 
113:         Route::get('/migrate', function () use ($redirectWithToast) {
114:             Artisan::call('migrate', [
115:                 '--force' => true,
116:             ]);
117: 
118:             return $redirectWithToast('success', 'Database migrated successfully.');
119:         })->name('migrate');
120: 
121:         Route::get('/seed', function () use ($redirectWithToast) {
122:             Artisan::call('db:seed', [
123:                 '--force' => true,
124:             ]);
125: 
126:             return $redirectWithToast('success', 'Database seeded successfully.');
127:         })->name('seed');
```

## E10 - Custom campaign routing

`TARGET:routes/web.php`

Lines 35-66:

```text
35: /*
36: |--------------------------------------------------------------------------
37: | Custom Root-Level Campaign Routes
38: |--------------------------------------------------------------------------
39: |
40: | These routes must remain after all fixed frontend/admin routes. This keeps
41: | application paths such as /admin, /track-order and /command protected from
42: | the dynamic one-segment campaign route.
43: |
44: */
45: $reservedCampaignRoutes = collect(config('campaign-routes.reserved', []))
46:     ->map(fn ($route) => preg_quote(strtolower((string) $route), '#'))
47:     ->filter()
48:     ->implode('|');
49: 
50: $campaignRoutePattern = config(
51:     'campaign-routes.pattern',
52:     '[a-z0-9][a-z0-9_-]*'
53: );
54: 
55: if ($reservedCampaignRoutes !== '') {
56:     $campaignRoutePattern = '(?!(?:' . $reservedCampaignRoutes . ')(?:/|$))'
57:         . $campaignRoutePattern;
58: }
59: 
60: Route::get('/{customRoute}', [CampaignPageController::class, 'showByCustomRoute'])
61:     ->where('customRoute', $campaignRoutePattern)
62:     ->name('campaign.custom.show');
63: 
64: Route::post('/{customRoute}/order', [CampaignOrderController::class, 'storeByCustomRoute'])
65:     ->where('customRoute', $campaignRoutePattern)
66:     ->name('campaign.custom.order.store');
```

## E10b - Baseline reserved paths

`TARGET:config/campaign-routes.php`

Lines 17-46:

```text
17:         'admin',
18:         'api',
19:         'assets',
20:         'build',
21:         'campaign',
22:         'command',
23:         'css',
24:         'favicon.ico',
25:         'fonts',
26:         'frontend',
27:         'home',
28:         'images',
29:         'js',
30:         'login',
31:         'logout',
32:         'password',
33:         'register',
34:         'robots.txt',
35:         'sitemap.xml',
36:         'storage',
37:         'success',
38:         'track-order',
39:         'up',
40:         'vendor',
41:         'webhooks',
42:     ],
43: 
44:     /* One URL segment only. Lowercase letters, numbers, hyphen and underscore. */
45:     'pattern' => '[a-z0-9][a-z0-9_-]*',
46: ];
```

## E11 - Custom public media storage

`TARGET:config/filesystems.php`

Lines 10-27:

```text
10: $configuredPublicRoot = trim((string) env('PUBLIC_DISK_ROOT', ''));
11: $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
12: 
13: $detectedPublicRoot = $documentRoot !== '' && is_dir($documentRoot)
14:     ? $normalizePath($documentRoot . DIRECTORY_SEPARATOR . 'storage')
15:     : $normalizePath(public_path('storage'));
16: 
17: $publicDiskRoot = $configuredPublicRoot !== ''
18:     ? $normalizePath($configuredPublicRoot)
19:     : $detectedPublicRoot;
20: 
21: $publicDiskUrl = rtrim(
22:     (string) env(
23:         'PUBLIC_DISK_URL',
24:         rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/storage'
25:     ),
26:     '/'
27: );
```

Lines 55-62:

```text
55:         'public' => [
56:             'driver' => 'local',
57:             'root' => $publicDiskRoot,
58:             'url' => $publicDiskUrl,
59:             'visibility' => 'public',
60:             'throw' => false,
61:             'report' => false,
62:         ],
```

Lines 79-89:

```text
79:     /*
80:     |--------------------------------------------------------------------------
81:     | Symbolic Links
82:     |--------------------------------------------------------------------------
83:     |
84:     | Public media is stored directly in the web-accessible storage directory.
85:     | Local and production environments do not require a symbolic link.
86:     |
87:     */
88: 
89:     'links' => [],
```

## E11b - Media version/path settings

`TARGET:config/media-library.php`

Lines 122-147:

```text
122:     /*
123:      * Here you can specify which path generator should be used for the given class.
124:      */
125:     'custom_path_generators' => [
126:         Product::class => ProductMediaPathGenerator::class,
127:         Campaign::class => LandingMediaPathGenerator::class,
128:         Review::class => LandingMediaPathGenerator::class,
129:     ],
130: 
131:     /*
132:      * When urls to files get generated, this class will be called. Use the default
133:      * if your files are stored locally above the site root or on s3.
134:      */
135:     'url_generator' => DefaultUrlGenerator::class,
136: 
137:     /*
138:      * Moves media on updating to keep path consistent. Enable it only with a custom
139:      * PathGenerator that uses, for example, the media UUID.
140:      */
141:     'moves_media_on_update' => false,
142: 
143:     /*
144:      * Whether to activate versioning when urls to files get generated.
145:      * When activated, this attaches a ?v=xx query string to the URL.
146:      */
147:     'version_urls' => false,
```

## E12 - Existing contacts

`TARGET:app/Models/SiteSetting.php`

Lines 16-40:

```text
16:         'phone',
17:         'hotline',
18:         'whatsapp_number',
19:         'messenger_link',
20:         'email',
21:         'address',
22:         'top_headline',
23:         'footer_text',
24:         'business_short_description',
25:         'working_hours',
26:         'status',
27:     ];
28: 
29:     protected $casts = [
30:         'status' => 'boolean',
31:     ];
32: 
33:     public function registerMediaCollections(): void
34:     {
35:         $this->addMediaCollection('site_logo')->useDisk('public')->singleFile();
36:         $this->addMediaCollection('site_white_logo')->useDisk('public')->singleFile();
37:         $this->addMediaCollection('site_favicon')->useDisk('public')->singleFile();
38:     }
39: 
40:     public function getLogoAttribute(): string
```

## E13 - Integer product money and stock

`TARGET:database/migrations/2026_05_03_051531_create_products_table.php`

Lines 14-45:

```text
14:         Schema::create('products', function (Blueprint $table) {
15:             $table->id();
16: 
17:             $table->foreignId('category_id')
18:                 ->constrained('categories')
19:                 ->cascadeOnDelete();
20: 
21:             $table->foreignId('brand_id')
22:                 ->nullable()
23:                 ->constrained('brands')
24:                 ->nullOnDelete();
25: 
26:             $table->string('name');
27:             $table->string('slug')->unique();
28:             $table->string('product_code')->unique();
29: 
30:             $table->unsignedInteger('purchase_price')->default(0);
31:             $table->unsignedInteger('old_price')->nullable();
32:             $table->unsignedInteger('new_price')->default(0);
33: 
34:             $table->unsignedInteger('stock')->default(0);
35:             $table->unsignedInteger('sold_quantity')->default(0);
36: 
37:             $table->string('weight_size')->nullable();
38: 
39:             $table->text('short_description')->nullable();
40:             $table->longText('full_description')->nullable();
41: 
42:             $table->boolean('is_top_sale')->default(false);
43:             $table->boolean('is_feature')->default(false);
44:             $table->boolean('is_flash_sale')->default(false);
45: 
```

## E13b - Integer order amounts

`TARGET:database/migrations/2026_05_03_052948_create_orders_table.php`

Lines 15-51:

```text
15:             $table->id();
16: 
17:             $table->string('invoice_id')->unique();
18: 
19:             $table->foreignId('campaign_id')
20:                 ->nullable()
21:                 ->constrained('campaigns')
22:                 ->nullOnDelete();
23: 
24:             $table->string('customer_name');
25:             $table->string('phone')->index();
26:             $table->text('address');
27: 
28:             $table->string('delivery_area')->nullable();
29: 
30:             $table->unsignedInteger('sub_total')->default(0);
31:             $table->unsignedInteger('shipping_charge')->default(0);
32:             $table->unsignedInteger('cod_charge')->default(0);
33:             $table->unsignedInteger('total_amount')->default(0);
34: 
35:             $table->string('payment_method')->default('cash_on_delivery');
36:             $table->string('payment_status')->default('cod_pending');
37: 
38:             $table->string('order_status')->default('pending');
39: 
40:             $table->boolean('is_fake')->default(false);
41:             $table->text('admin_note')->nullable();
42:             $table->text('customer_note')->nullable();
43: 
44:             $table->string('source_ip')->nullable();
45:             $table->text('user_agent')->nullable();
46:             $table->text('source_url')->nullable();
47: 
48:             $table->timestamp('confirmed_at')->nullable();
49:             $table->timestamp('delivered_at')->nullable();
50:             $table->timestamp('cancelled_at')->nullable();
51:             $table->timestamp('marked_fake_at')->nullable();
```

## E13c - Decimal discount column

`TARGET:database/migrations/2026_06_01_095911_add_discount_amount_to_order_items_table.php`

Lines 10-22:

```text
10:     {
11:         Schema::table('order_items', function (Blueprint $table) {
12:             if (! Schema::hasColumn('order_items', 'discount_amount')) {
13:                 $table->decimal('discount_amount', 10, 2)->default(0)->after('unit_price');
14:             }
15:         });
16:     }
17: 
18:     public function down(): void
19:     {
20:         Schema::table('order_items', function (Blueprint $table) {
21:             if (Schema::hasColumn('order_items', 'discount_amount')) {
22:                 $table->dropColumn('discount_amount');
```

## E13d - Manual line monetary arithmetic

`TARGET:app/Http/Controllers/Admin/OrderController.php`

Lines 2734-2778:

```text
2734:                 if (! $product) {
2735:                     continue;
2736:                 }
2737: 
2738:                 $resolvedProductIds[] = (int) $product->id;
2739: 
2740:                 $quantity        = max(1, (int) $row['quantity']);
2741:                 $unitPrice       = max(0, (float) $row['unit_price']);
2742:                 $discountAmount  = max(0, (float) ($row['discount_amount'] ?? 0));
2743:                 $grossLineTotal  = $quantity * $unitPrice;
2744:                 $lineTotal       = max(0, $grossLineTotal - $discountAmount);
2745:                 $subTotal       += $lineTotal;
2746: 
2747:                 $item = null;
2748: 
2749:                 if (! empty($row['id'])) {
2750:                     $item = $order->items()->whereKey((int) $row['id'])->first();
2751:                 }
2752: 
2753:                 if (! $item) {
2754:                     $item = new OrderItem(['order_id' => $order->id]);
2755:                 }
2756: 
2757:                 $item->fill([
2758:                     'order_id'        => $order->id,
2759:                     'product_id'      => $product->id,
2760:                     'product_name'    => $product->name,
2761:                     'unit_price'      => $unitPrice,
2762:                     'quantity'        => $quantity,
2763:                     'discount_amount' => $discountAmount,
2764:                     'total_price'     => $lineTotal,
2765:                 ])->save();
2766: 
2767:                 $keepItemIds[] = $item->id;
2768:             }
2769: 
2770:             if (! count($keepItemIds)) {
2771:                 return back()->withInput()->with('error', 'Please add at least one valid product.');
2772:             }
2773: 
2774:             $order->items()->whereNotIn('id', $keepItemIds)->delete();
2775: 
2776:             $courier        = null;
2777:             $courierAccount = null;
2778: 
```

## E14 - Existing order status/source fields

`TARGET:app/Models/Order.php`

Lines 19-60:

```text
19:     public const STATUS_PENDING    = 'pending';
20:     public const STATUS_CONFIRMED  = 'confirmed';
21:     public const STATUS_PROCESSING = 'processing';
22:     public const STATUS_SHIPPED          = 'shipped';
23:     public const STATUS_DELIVERED        = 'delivered';
24:     public const STATUS_CANCELLED        = 'cancelled';
25:     public const STATUS_CANCELED         = 'canceled';
26:     public const STATUS_COURIER_PENDING   = 'courier_pending';
27:     public const STATUS_COURIER_CANCELLED = 'courier_cancelled';
28:     public const STATUS_FAKE             = 'fake';
29:     public const STATUS_STOCK_OUT        = 'stock_out';
30:     public const STATUS_COMPLETE_INVOICE = 'complete_invoice';
31: 
32:     public const PAYMENT_COD                = 'cash_on_delivery';
33:     public const PAYMENT_STATUS_UNPAID      = 'unpaid';
34:     public const PAYMENT_STATUS_COD_PENDING = 'cod_pending';
35:     public const PAYMENT_STATUS_COLLECTED   = 'collected';
36:     public const PAYMENT_STATUS_FAILED      = 'failed';
37: 
38:     public const CUSTOM_LIST_ONE = 'order_list_1';
39:     public const CUSTOM_LIST_TWO = 'order_list_2';
40: 
41:     public const CREATED_VIA_FRONTEND        = 'frontend';
42:     public const CREATED_VIA_ADMIN_MANUAL    = 'admin_manual';
43:     public const CREATED_VIA_EMPLOYEE_MANUAL = 'employee_manual';
44:     public const CREATED_VIA_ADMIN_BULK      = 'admin_bulk';
45:     public const CREATED_VIA_EMPLOYEE_BULK   = 'employee_bulk';
46:     public const CREATED_VIA_EXTERNAL_API    = 'external_api';
47: 
48:     protected $fillable = [
49:         'invoice_id',
50:         'success_token',
51:         'campaign_id',
52:         'customer_id',
53:         'bulk_order_batch_id',
54:         'assigned_employee_id',
55:         'created_via',
56:         'created_by_admin_id',
57:         'external_website_id',
58:         'external_order_id',
59:         'external_payload',
60:         'api_received_at',
```

## E15 - Page-local cart selection

`TARGET:resources/views/frontend/pages/home.blade.php`

Lines 3742-3752:

```text
3742:     const defaultDeliveryArea = Object.keys(deliveryCharges)[0] || '';
3743: 
3744:     let selectedCategory = 'all';
3745:     let selectedBrand = 'all';
3746:     let selectedProducts = {};
3747:     let suppressTracking = false;
3748: 
3749:     function money(amount) {
3750:         return '৳' + Number(amount || 0).toLocaleString('en-US');
3751:     }
3752: 
```

Lines 3992-4005:

```text
3992:                     </div>
3993:                 </div>
3994:             `;
3995: 
3996:             inputsHtml += `
3997:                 <input type="hidden" name="products[${index}][id]" value="${item.id}">
3998:                 <input type="hidden" name="products[${index}][quantity]" value="${item.quantity}">
3999:             `;
4000: 
4001:             index++;
4002:         });
4003: 
4004:         if (!Object.keys(selectedProducts).length) {
4005:             itemsHtml = `
```

## E16 - Leatherket sections are category/max/numeric sort

`REFERENCE:app/Http/Controllers/Admin/SectionController.php`

Lines 18-20:

```text
18:     {
19:         
20:         return datatables()->of(Section::with('category')->orderBy('sort')->get())
```

Lines 35-41:

```text
35:     public function store(Request $request)
36:     {
37:         $section = new Section();
38:         $section->category_id = $request->category_id;
39:         $section->max = $request->max;
40:         $section->sort = $request->sort;
41:         $result = $section->save();
```

Lines 58-64:

```text
58:     public function update(Request $request, $id)
59:     {
60:         $section = Section::find($id);
61:          $section->category_id = $request->category_id;
62:         $section->max = $request->max;
63:         $section->sort = $request->sort;
64:         $result = $section->save();
```

## E17 - Leatherket campaign cart clearing

`REFERENCE:app/Http/Controllers/WebsiteController.php`

Lines 74-106:

```text
74:     public function campaign($slug)
75:     {
76:         $campaign_data = Campaign::with(['products.options', 'products.colors', 'products.sizes', 'product.options', 'product.colors', 'product.sizes'])
77:             ->where('slug', $slug)
78:             ->first();
79: 
80:         if (!$campaign_data) {
81:             abort(404);
82:         }
83: 
84:         if (session_status() !== PHP_SESSION_ACTIVE) {
85:             session_start();
86:         }
87: 
88:         $charge = ShippingCharge::first();
89:         $_SESSION['delivery'] = $charge ? (float) $charge->charge : 0;
90: 
91:         // Prefer the new ordered many-to-many campaign products. Existing
92:         // campaigns that only have product_id continue to work unchanged.
93:         $campaignProducts = $campaign_data->products->filter(function ($p) { return $p->status === 'Active'; })->values();
94:         if ($campaignProducts->isEmpty() && $campaign_data->product && $campaign_data->product->status === 'Active') {
95:             $campaignProducts = collect([$campaign_data->product]);
96:         }
97: 
98:         if ($campaignProducts->isEmpty()) {
99:             abort(404);
100:         }
101: 
102:         Cart::destroy();
103: 
104:         // Campaign can contain many products, but only Product 1 is selected
105:         // by default. Customers can add more campaign products from the large
106:         // product selector in the order section.
```

## E18 - Raw HTML slots require sanitizer audit

`TARGET:resources/views/frontend/pages/home.blade.php`

Lines 2646-2654:

```text
2646:         <div class="row align-items-center">
2647:             <div class="col-lg-7 hero-content">
2648:                 <h1 class="hero-title">{{ $heroTitle }}</h1>
2649: 
2650:                 <div class="hero-text">
2651:                     {!! $heroSubtitle !!}
2652:                 </div>
2653: 
2654:                 @if($isBenefitsSectionActive && $benefits->isNotEmpty())
```

Lines 3140-3149:

```text
3140:                     <i class="fas fa-chevron-down"></i>
3141:                 </button>
3142: 
3143:                 <div id="faq_{{ $faqId }}" class="collapse"
3144:                     data-parent="#faqAccordion">
3145:                     <div class="faq-body">
3146:                         {!! $answer !!}
3147:                     </div>
3148:                 </div>
3149:             </div>
```

## E19 - Shipping scope is exact

`TARGET:app/Models/ShippingCharge.php`

Lines 39-59:

```text
39:      * No runtime fallback is used. Campaign pages and order forms therefore
40:      * always read the delivery areas and charges configured for that campaign.
41:      * A null campaign id is reserved for legacy/global rows used outside a
42:      * campaign context.
43:      */
44:     public static function resolvedForCampaign(?int $campaignId, bool $activeOnly = true): Collection
45:     {
46:         $query = static::query()
47:             ->when(
48:                 $campaignId !== null,
49:                 fn ($query) => $query->where('campaign_id', $campaignId),
50:                 fn ($query) => $query->whereNull('campaign_id')
51:             );
52: 
53:         if ($activeOnly) {
54:             $query->active();
55:         }
56: 
57:         return $query->orderBy('id')->get();
58:     }
59: }
```

## Inventory and execution evidence

The target contains no tests/, vendor/ or node_modules/ entries. No meeting PDF/DOCX exists in the inspected archives. The homepage Blade has 4,282 lines in this snapshot. Runtime parser results are in reports/php-syntax-check.json. Application/DB/browser checks were not executed.
