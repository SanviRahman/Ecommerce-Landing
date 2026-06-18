<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Title
    |--------------------------------------------------------------------------
    |
    | Here you can change the default title of your admin panel.
    |
    | For detailed instructions you can look the title section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'title'                                   => 'AdminLTE 3',
    'title_prefix'                            => '',
    'title_postfix'                           => '',

    /*
    |--------------------------------------------------------------------------
    | Favicon
    |--------------------------------------------------------------------------
    |
    | Here you can activate the favicon.
    |
    | For detailed instructions you can look the favicon section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_ico_only'                            => true,
    'use_full_favicon'                        => false,

    /*
    |--------------------------------------------------------------------------
    | Google Fonts
    |--------------------------------------------------------------------------
    |
    | Here you can allow or not the use of external google fonts. Disabling the
    | google fonts may be useful if your admin panel internet access is
    | restricted somehow.
    |
    | For detailed instructions you can look the google fonts section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'google_fonts'                            => [
        'allowed' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Logo
    |--------------------------------------------------------------------------
    |
    | Here you can change the logo of your admin panel.
    |
    | For detailed instructions you can look the logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'logo'                                    => '<b>Admin</b>LTE',
    'logo_img'                                => 'vendor/adminlte/dist/img/AdminLTELogo.png',
    'logo_img_class'                          => 'brand-image img-circle elevation-3',
    'logo_img_xl'                             => null,
    'logo_img_xl_class'                       => 'brand-image-xs',
    'logo_img_alt'                            => 'Admin Logo',

    /*
    |--------------------------------------------------------------------------
    | Authentication Logo
    |--------------------------------------------------------------------------
    |
    | Here you can setup an alternative logo to use on your login and register
    | screens. When disabled, the admin panel logo will be used instead.
    |
    | For detailed instructions you can look the auth logo section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'auth_logo'                               => [
        'enabled' => false,
        'img'     => [
            'path'   => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt'    => 'Auth Logo',
            'class'  => '',
            'width'  => 50,
            'height' => 50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preloader Animation
    |--------------------------------------------------------------------------
    |
    | Here you can change the preloader animation configuration. Currently, two
    | modes are supported: 'fullscreen' for a fullscreen preloader animation
    | and 'cwrapper' to attach the preloader animation into the content-wrapper
    | element and avoid overlapping it with the sidebars and the top navbar.
    |
    | For detailed instructions you can look the preloader section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'preloader'                               => [
        'enabled' => true,
        'mode'    => 'fullscreen',
        'img'     => [
            'path'   => 'vendor/adminlte/dist/img/AdminLTELogo.png',
            'alt'    => 'AdminLTE Preloader Image',
            'effect' => 'animation__shake',
            'width'  => 60,
            'height' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Menu
    |--------------------------------------------------------------------------
    |
    | Here you can activate and change the user menu.
    |
    | For detailed instructions you can look the user menu section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'usermenu_enabled'                        => true,
    'usermenu_header'                         => false,
    'usermenu_header_class'                   => 'bg-primary',
    'usermenu_image'                          => false,
    'usermenu_desc'                           => false,
    'usermenu_profile_url'                    => false,

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | Here we change the layout of your admin panel.
    |
    | For detailed instructions you can look the layout section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'layout_topnav'                           => null,
    'layout_boxed'                            => null,
    'layout_fixed_sidebar'                    => null,
    'layout_fixed_navbar'                     => null,
    'layout_fixed_footer'                     => null,
    'layout_dark_mode'                        => null,

    /*
    |--------------------------------------------------------------------------
    | Authentication Views Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the authentication views.
    |
    | For detailed instructions you can look the auth classes section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_auth_card'                       => 'card-outline card-primary',
    'classes_auth_header'                     => '',
    'classes_auth_body'                       => '',
    'classes_auth_footer'                     => '',
    'classes_auth_icon'                       => '',
    'classes_auth_btn'                        => 'btn-flat btn-primary',

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Classes
    |--------------------------------------------------------------------------
    |
    | Here you can change the look and behavior of the admin panel.
    |
    | For detailed instructions you can look the admin panel classes here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'classes_body'                            => '',
    'classes_brand'                           => '',
    'classes_brand_text'                      => '',
    'classes_content_wrapper'                 => '',
    'classes_content_header'                  => '',
    'classes_content'                         => '',
    'classes_sidebar'                         => 'sidebar-dark-primary elevation-4',
    'classes_sidebar_nav'                     => '',
    'classes_topnav'                          => 'navbar-white navbar-light',
    'classes_topnav_nav'                      => 'navbar-expand',
    'classes_topnav_container'                => 'container',

    /*
    |--------------------------------------------------------------------------
    | Sidebar
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar of the admin panel.
    |
    | For detailed instructions you can look the sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'sidebar_mini'                            => 'lg',
    'sidebar_collapse'                        => false,
    'sidebar_collapse_auto_size'              => false,
    'sidebar_collapse_remember'               => false,
    'sidebar_collapse_remember_no_transition' => true,
    'sidebar_scrollbar_theme'                 => 'os-theme-light',
    'sidebar_scrollbar_auto_hide'             => 'l',
    'sidebar_nav_accordion'                   => true,
    'sidebar_nav_animation_speed'             => 300,

    /*
    |--------------------------------------------------------------------------
    | Control Sidebar (Right Sidebar)
    |--------------------------------------------------------------------------
    |
    | Here we can modify the right sidebar aka control sidebar of the admin panel.
    |
    | For detailed instructions you can look the right sidebar section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Layout-and-Styling-Configuration
    |
    */

    'right_sidebar'                           => false,
    'right_sidebar_icon'                      => 'fas fa-cogs',
    'right_sidebar_theme'                     => 'dark',
    'right_sidebar_slide'                     => true,
    'right_sidebar_push'                      => true,
    'right_sidebar_scrollbar_theme'           => 'os-theme-light',
    'right_sidebar_scrollbar_auto_hide'       => 'l',

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Here we can modify the url settings of the admin panel.
    |
    | For detailed instructions you can look the urls section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Basic-Configuration
    |
    */

    'use_route_url'                           => false,
    'dashboard_url'                           => 'home',
    'logout_url'                              => 'logout',
    'login_url'                               => 'login',
    'register_url'                            => 'register',
    'password_reset_url'                      => 'password/reset',
    'password_email_url'                      => 'password/email',
    'profile_url'                             => false,
    'disable_darkmode_routes'                 => false,

    /*
    |--------------------------------------------------------------------------
    | Laravel Asset Bundling
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Laravel Asset Bundling option for the admin panel.
    | Currently, the next modes are supported: 'mix', 'vite' and 'vite_js_only'.
    | When using 'vite_js_only', it's expected that your CSS is imported using
    | JavaScript. Typically, in your application's 'resources/js/app.js' file.
    | If you are not using any of these, leave it as 'false'.
    |
    | For detailed instructions you can look the asset bundling section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'laravel_asset_bundling'                  => false,
    'laravel_css_path'                        => 'css/app.css',
    'laravel_js_path'                         => 'js/app.js',

    /*
    |--------------------------------------------------------------------------
    | Menu Items
    |--------------------------------------------------------------------------
    |
    | Here we can modify the sidebar/top navigation of the admin panel.
    |
    | For detailed instructions you can look here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'menu'                                    => [
        /*
    |--------------------------------------------------------------------------
    | Top Navbar Items
    |--------------------------------------------------------------------------
    */
        [
            'type'         => 'navbar-search',
            'text'         => 'search',
            'topnav_right' => true,
        ],
        [
            'type'         => 'fullscreen-widget',
            'topnav_right' => true,
        ],

        /*
    |--------------------------------------------------------------------------
    | Sidebar Search
    |--------------------------------------------------------------------------
    */
        [
            'type' => 'sidebar-menu-search',
            'text' => 'search',
        ],

        /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
        [
            'text' => 'Dashboard',
            'url'  => 'admin',
            'icon' => 'fas fa-fw fa-tachometer-alt',
            'can'  => 'admin-or-employee',
        ],

        /*
    |--------------------------------------------------------------------------
    | Order Management
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'ORDER MANAGEMENT',
            'can'    => 'admin-or-employee',
        ],
        [
            'text'    => 'Orders',
            'url'     => '#',
            'icon'    => 'fas fa-shopping-cart',
            'can'     => 'admin-or-employee',
            'submenu' => [
                [
                    'text'   => 'New Orders',
                    'url'    => 'admin/orders',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders'],
                ],
                [
                    'text'   => 'All Orders',
                    'url'    => 'admin/orders/all',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/all*'],
                ],
                [
                    'text'   => 'Pending Orders',
                    'url'    => 'admin/orders/pending',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/pending*'],
                ],
                [
                    'text'   => 'Complete Orders',
                    'url'    => 'admin/orders/confirmed',
                    'icon'   => 'far fa-check-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/confirmed*'],
                ],
                [
                    'text'   => 'Cancelled Orders',
                    'url'    => 'admin/orders/cancelled',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/cancelled*'],
                ],
                [
                    'text'   => 'Order List 1',
                    'url'    => 'admin/orders/order-list-1',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/order-list-1*'],
                ],
                [
                    'text'   => 'Order List 2',
                    'url'    => 'admin/orders/order-list-2',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/order-list-2*'],
                ],
                [
                    'text'   => 'Shipped',
                    'url'    => 'admin/orders/shipped',
                    'icon'   => 'fas fa-truck',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/shipped*'],
                ],
                [
                    'text'   => 'Delivered',
                    'url'    => 'admin/orders/delivered',
                    'icon'   => 'fas fa-check-double',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/delivered*'],
                ],
                [
                    'text'   => 'Stock Out',
                    'url'    => 'admin/orders/stock-out',
                    'icon'   => 'fas fa-box-open',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/stock-out*'],
                ],
                [
                    'text'   => 'Fake Orders',
                    'url'    => 'admin/orders/fake',
                    'icon'   => 'fas fa-exclamation-triangle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/fake*'],
                ],
            ],
        ],

        [
            'text'    => 'Invoice Management',
            'url'     => '#',
            'icon'    => 'fas fa-file-invoice',
            'can'     => 'admin-or-employee',
            'submenu' => [
                [
                    'text'   => 'Pending Invoice',
                    'url'    => 'admin/orders/invoices/pending',
                    'icon'   => 'far fa-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/invoices/pending*'],
                ],
                [
                    'text'   => 'Complete Invoice',
                    'url'    => 'admin/orders/invoices/complete',
                    'icon'   => 'far fa-check-circle',
                    'can'    => 'admin-or-employee',
                    'active' => ['admin/orders/invoices/complete*'],
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Product Management
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'PRODUCT MANAGEMENT',
            'can'    => 'admin-or-employee',
        ],
        [
            'text'    => 'Categories',
            'icon'    => 'fas fa-fw fa-list',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Categories',
                    'url'  => 'admin/categories',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Brands',
            'icon'    => 'fas fa-fw fa-tags',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Brands',
                    'url'  => 'admin/brands',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Products',
            'icon'    => 'fas fa-fw fa-box',
            'can'     => 'admin-or-employee',
            'submenu' => [
                [
                    'text' => 'All Products',
                    'url'  => 'admin/products',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-or-employee',
                ],
            ],
        ],

/*
|--------------------------------------------------------------------------
| Landing Page Management
|--------------------------------------------------------------------------
*/
        [
            'header' => 'LANDING PAGE',
            'can'    => 'admin-only',
        ],
        [
            'text'    => 'Landing Pages',
            'icon'    => 'fas fa-fw fa-bullhorn',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'Landing Page Manage',
                    'url'  => 'admin/campaigns',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Landing Page Create',
                    'url'  => 'admin/campaigns/create',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Create Pages',
            'icon'    => 'fas fa-fw fa-file-alt',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Pages',
                    'url'  => 'admin/pages',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Create Page',
                    'url'  => 'admin/pages/create',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Banners & Ads',
            'icon'    => 'fas fa-fw fa-images',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Banners',
                    'url'  => 'admin/banners',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Marketing
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'MARKETING',
            'can'    => 'admin-only',
        ],
        [
            'text'    => 'Pixel & GTM',
            'icon'    => 'fas fa-fw fa-code',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'Tracking Pixels',
                    'url'  => 'admin/tracking-pixels',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Create Pixel',
                    'url'  => 'admin/tracking-pixels/create',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Social Media',
            'icon'    => 'fas fa-fw fa-share-alt',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Social Media',
                    'url'  => 'admin/social-media',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],

        /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'REPORTS',
            'can'    => 'admin-only',
        ],
        [
            'text'    => 'Reports',
            'icon'    => 'fas fa-fw fa-chart-line',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'Report Manage',
                    'url'  => 'admin/reports',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Generate Report',
                    'url'  => 'admin/reports/create',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],

        /*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/
        [
            'header' => 'SETTINGS',
            'can'    => 'admin-only',
        ],
        [
            'text' => 'Site Settings',
            'url'  => 'admin/site-settings',
            'icon' => 'fas fa-fw fa-cogs',
            'can'  => 'admin-only',
        ],
        [
            'text'    => 'API Integration',
            'icon'    => 'fas fa-fw fa-plug',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'Courier API',
                    'url'  => 'admin/courier-accounts',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Add Courier',
                    'url'  => 'admin/couriers',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        [
            'text'    => 'Admins & Employees',
            'icon'    => 'fas fa-fw fa-users-cog',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'All Users',
                    'url'  => 'admin/users',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
            ],
        ],
        /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'ACCOUNT',
            'can'    => 'admin-or-employee',
        ],
        [
            'text' => 'Profile',
            'url'  => 'admin/profile',
            'icon' => 'fas fa-fw fa-user',
            'can'  => 'admin-or-employee',
        ],
        [
            'text' => 'Change Password',
            'url'  => 'admin/password',
            'icon' => 'fas fa-fw fa-lock',
            'can'  => 'admin-or-employee',
        ],

        /*
    |--------------------------------------------------------------------------
    | Media Management
    |--------------------------------------------------------------------------
    */
        [
            'header' => 'MEDIA MANAGEMENT',
            'can'    => 'admin-only',
        ],
        [
            'text'    => 'Media Management',
            'url'     => '#',
            'icon'    => 'fas fa-fw fa-photo-video',
            'can'     => 'admin-only',
            'active'  => ['admin/media-management*'],
            'submenu' => [
                [
                    'text'   => 'All Media',
                    'url'    => 'admin/media-management',
                    'icon'   => 'far fa-fw fa-circle',
                    'can'    => 'admin-only',
                    'active' => ['admin/media-management'],
                ],
                [
                    'text'   => 'Category Media',
                    'url'    => 'admin/media-management/category',
                    'icon'   => 'far fa-fw fa-circle',
                    'can'    => 'admin-only',
                    'active' => ['admin/media-management/category*'],
                ],
                [
                    'text'   => 'Products Media',
                    'url'    => 'admin/media-management/products',
                    'icon'   => 'far fa-fw fa-circle',
                    'can'    => 'admin-only',
                    'active' => ['admin/media-management/products*'],
                ],
                [
                    'text'    => 'Campaign Media',
                    'url'     => 'admin/media-management/campaign',
                    'icon'    => 'far fa-fw fa-circle',
                    'can'     => 'admin-only',
                    'active'  => ['admin/media-management/campaign*'],
                    'submenu' => [
                        [
                            'text'   => 'All Campaign Media',
                            'url'    => 'admin/media-management/campaign',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign'],
                        ],
                        [
                            'text'   => 'Hero Section Media',
                            'url'    => 'admin/media-management/campaign/hero',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign/hero*'],
                        ],
                        [
                            'text'   => 'Section Images',
                            'url'    => 'admin/media-management/campaign/section',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign/section*'],
                        ],
                        [
                            'text'   => 'Product Gallery',
                            'url'    => 'admin/media-management/campaign/product',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign/product*'],
                        ],
                        [
                            'text'   => 'Review Media',
                            'url'    => 'admin/media-management/campaign/review',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign/review*'],
                        ],
                        [
                            'text'   => 'Gallery Media',
                            'url'    => 'admin/media-management/campaign/gallery',
                            'icon'   => 'far fa-dot-circle',
                            'can'    => 'admin-only',
                            'active' => ['admin/media-management/campaign/gallery*'],
                        ],
                    ],
                ],
                [
                    'text'   => 'Other Media',
                    'url'    => 'admin/media-management/other',
                    'icon'   => 'far fa-fw fa-circle',
                    'can'    => 'admin-only',
                    'active' => ['admin/media-management/other*'],
                ],
            ],
        ],

        /*
|--------------------------------------------------------------------------
| Commands
|--------------------------------------------------------------------------
*/
        [
            'header' => 'COMMANDS',
            'can'    => 'admin-only',
        ],
        [
            'text'    => 'Commands',
            'icon'    => 'fas fa-fw fa-terminal',
            'can'     => 'admin-only',
            'submenu' => [
                [
                    'text' => 'Clear Cache',
                    'url'  => 'command/clear-cache',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Clear Config',
                    'url'  => 'command/clear-config',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Clear Route',
                    'url'  => 'command/clear-route',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Clear View',
                    'url'  => 'command/clear-view',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Clear Events',
                    'url'  => 'command/clear-events',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Optimize',
                    'url'  => 'command/optimize',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Optimize Clear',
                    'url'  => 'command/optimize-clear',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Migrate',
                    'url'  => 'command/migrate',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Seed',
                    'url'  => 'command/seed',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Storage Link',
                    'url'  => 'command/storage-link',
                    'icon' => 'far fa-fw fa-circle',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Fresh Migrate',
                    'url'  => 'command/migrate-fresh',
                    'icon' => 'far fa-fw fa-circle text-danger',
                    'can'  => 'admin-only',
                ],
                [
                    'text' => 'Fresh Migrate Seed',
                    'url'  => 'command/migrate-fresh-seed',
                    'icon' => 'far fa-fw fa-circle text-danger',
                    'can'  => 'admin-only',
                ],
            ],
        ],
    ],

/*
    |--------------------------------------------------------------------------
    | Menu Filters
    |--------------------------------------------------------------------------
    |
    | Here we can modify the menu filters of the admin panel.
    |
    | For detailed instructions you can look the menu filters section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Menu-Configuration
    |
    */

    'filters'                                 => [
        JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\HrefFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\SearchFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ActiveFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\ClassesFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\LangFilter::class,
        JeroenNoten\LaravelAdminLte\Menu\Filters\DataFilter::class,
    ],

/*
    |--------------------------------------------------------------------------
    | Plugins Initialization
    |--------------------------------------------------------------------------
    |
    | Here we can modify the plugins used inside the admin panel.
    |
    | For detailed instructions you can look the plugins section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Plugins-Configuration
    |
    */

    'plugins'                                 => [
        'Datatables'  => [
            'active' => false,
            'files'  => [
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js',
                ],
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdn.datatables.net/1.10.19/js/dataTables.bootstrap4.min.js',
                ],
                [
                    'type'     => 'css',
                    'asset'    => false,
                    'location' => '//cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css',
                ],
            ],
        ],

        'Select2'     => [
            'active' => true,
            'files'  => [
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
                ],
                [
                    'type'     => 'css',
                    'asset'    => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.css',
                ],
            ],
        ],

        'Chartjs'     => [
            'active' => true,
            'files'  => [
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.0/Chart.bundle.min.js',
                ],
            ],
        ],

        'Sweetalert2' => [
            'active' => true,
            'files'  => [
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdn.jsdelivr.net/npm/sweetalert2@11',
                ],
            ],
        ],

        'Pace'        => [
            'active' => false,
            'files'  => [
                [
                    'type'     => 'css',
                    'asset'    => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/themes/blue/pace-theme-center-radar.min.css',
                ],
                [
                    'type'     => 'js',
                    'asset'    => false,
                    'location' => '//cdnjs.cloudflare.com/ajax/libs/pace/1.0.2/pace.min.js',
                ],
            ],
        ],

        'AdminToast'  => [
            'active' => true,
            'files'  => [
                [
                    'type'     => 'js',
                    'asset'    => true,
                    'location' => 'assets/admin/js/admin-toast.js',
                ],
            ],
        ],
    ],

/*
    |--------------------------------------------------------------------------
    | IFrame
    |--------------------------------------------------------------------------
    |
    | Here we change the IFrame mode configuration. Note these changes will
    | only apply to the view that extends and enable the IFrame mode.
    |
    | For detailed instructions you can look the iframe mode section here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/IFrame-Mode-Configuration
    |
    */

    'iframe'                                  => [
        'default_tab' => [
            'url'   => null,
            'title' => null,
        ],
        'buttons'     => [
            'close'           => true,
            'close_all'       => true,
            'close_all_other' => true,
            'scroll_left'     => true,
            'scroll_right'    => true,
            'fullscreen'      => true,
        ],
        'options'     => [
            'loading_screen'    => 1000,
            'auto_show_new_tab' => true,
            'use_navbar_items'  => true,
        ],
    ],

/*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | Here we can enable the Livewire support.
    |
    | For detailed instructions you can look the livewire here:
    | https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Other-Configuration
    |
    */

    'livewire'                                => false,
];
