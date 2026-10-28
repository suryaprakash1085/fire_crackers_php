<?php
use App\Controllers\{HomeController, PageController, ProductController, ApiController, ShopController};
use App\Controllers\Admin\{AuthController, DashboardController, ProductsController, CrudController, OrdersController, EnquiriesController, ContentController, SettingsController, AdminUsersController, ReportsController, CompanyController};

$r = $router;
$r->get('/', [HomeController::class, 'index']);
$r->get('/about', [PageController::class, 'about']);
$r->get('/services', [PageController::class, 'services']);
$r->get('/products', [ProductController::class, 'index']);
$r->get('/product-details.php', [ProductController::class, 'show']);
$r->get('/product', [ProductController::class, 'show']);
$r->get('/quick-order', [ShopController::class, 'quickOrder']);
$r->get('/voice-order', [ShopController::class, 'voiceOrder']);
$r->get('/cart', [ShopController::class, 'cart']);
$r->get('/checkout', [ShopController::class, 'checkout']);
$r->post('/checkout', [ShopController::class, 'place']);
$r->get('/order-success', [ShopController::class, 'success']);
$r->get('/track', [ShopController::class, 'track']);
$r->post('/track', [ShopController::class, 'trackSubmit']);
$r->get('/contact', [PageController::class, 'contact']);
$r->post('/contact', [PageController::class, 'contactSubmit']);
$r->get('/policy/{slug}', [PageController::class, 'policy']);

$r->get('/api/cart', [ApiController::class, 'cart']);
$r->post('/api/cart/add', [ApiController::class, 'add']);
$r->post('/api/cart/add-many', [ApiController::class, 'addMany']);
$r->post('/api/cart/update', [ApiController::class, 'update']);
$r->post('/api/cart/remove', [ApiController::class, 'remove']);
$r->post('/api/cart/clear', [ApiController::class, 'clear']);
$r->get('/api/products/lookup', [ApiController::class, 'lookup']);
$r->post('/api/voice/parse', [ApiController::class, 'voice']);
$r->post('/api/voice/undo', [ApiController::class, 'voiceUndo']);

// ---- Admin ----
$r->any('/admin/login', [AuthController::class, 'login']);
$r->post('/admin/logout', [AuthController::class, 'logout']);
$r->get('/admin', [DashboardController::class, 'index']);
$r->get('/admin/dashboard', [DashboardController::class, 'index']);

$r->get('/admin/products', [ProductsController::class, 'index']);
$r->get('/admin/products/create', [ProductsController::class, 'form']);
$r->get('/admin/products/template', [ProductsController::class, 'template']);
$r->get('/admin/products/import', [ProductsController::class, 'importForm']);
$r->post('/admin/products/import', [ProductsController::class, 'importSubmit']);
$r->get('/admin/products/{id}/edit', [ProductsController::class, 'form']);
$r->get('/admin/products/{id}', [ProductsController::class, 'show']);
$r->post('/admin/products/save', [ProductsController::class, 'save']);
$r->post('/admin/products/{id}/archive', [ProductsController::class, 'archive']);
$r->post('/admin/products/{id}/delete', [ProductsController::class, 'delete']);
$r->post('/admin/products/{id}/restore', [ProductsController::class, 'restore']);

foreach (['categories', 'services', 'banners'] as $e) {
    $r->get("/admin/$e", [CrudController::class, 'index_' . $e]);
    $r->get("/admin/$e/create", [CrudController::class, 'form_' . $e]);
    $r->get("/admin/$e/{id}/edit", [CrudController::class, 'form_' . $e]);
    $r->post("/admin/$e/save", [CrudController::class, 'save_' . $e]);
    $r->post("/admin/$e/{id}/delete", [CrudController::class, 'delete_' . $e]);
}

$r->get('/admin/orders', [OrdersController::class, 'index']);
$r->get('/admin/orders/export', [OrdersController::class, 'export']);
$r->get('/admin/orders/{id}', [OrdersController::class, 'show']);
$r->get('/admin/orders/{id}/print', [OrdersController::class, 'printView']);
$r->post('/admin/orders/{id}/status', [OrdersController::class, 'status']);
$r->post('/admin/orders/{id}/details', [OrdersController::class, 'details']);

$r->get('/admin/reports', [ReportsController::class, 'index']);
$r->get('/admin/reports/export', [ReportsController::class, 'export']);
$r->get('/admin/company', [CompanyController::class, 'index']);
$r->post('/admin/company', [CompanyController::class, 'save']);

$r->get('/admin/enquiries', [EnquiriesController::class, 'index']);
$r->post('/admin/enquiries/{id}', [EnquiriesController::class, 'update']);

$r->get('/admin/content', [ContentController::class, 'index']);
$r->post('/admin/content/page', [ContentController::class, 'savePage']);
$r->post('/admin/content/settings', [ContentController::class, 'saveSettings']);


$r->get('/admin/customize', [SettingsController::class, 'customize']);
$r->post('/admin/customize', [SettingsController::class, 'saveCustomize']);
$r->get('/admin/smtp', [SettingsController::class, 'smtp']);
$r->post('/admin/smtp', [SettingsController::class, 'saveSmtp']);
$r->post('/admin/smtp/test', [SettingsController::class, 'testSmtp']);

$r->get('/admin/users', [AdminUsersController::class, 'index']);
$r->post('/admin/users/save', [AdminUsersController::class, 'save']);
$r->post('/admin/users/{id}/toggle', [AdminUsersController::class, 'toggle']);
$r->get('/admin/account', [AdminUsersController::class, 'account']);
$r->post('/admin/account/password', [AdminUsersController::class, 'password']);
