<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Services\{MediaService, ProductImportStash, ProductSheet, ProductUploadStash, SkuService, Xlsx};

final class ProductsController extends AdminController {
    protected string $minRole = 'admin';

    public function index(): void {
        $w = ['1=1']; $p = [];
        $q = $this->input('q'); $cat = (int)($_GET['category'] ?? 0); $st = $this->input('status'); $stock = $this->input('stock');
        if ($q !== '') { $w[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $p[] = "%$q%"; $p[] = "%$q%"; }
        if ($cat) { $w[] = 'p.category_id=?'; $p[] = $cat; }
        if (in_array($st, ['active', 'inactive', 'archived'], true)) { $w[] = 'p.status=?'; $p[] = $st; } else $w[] = "p.status<>'archived'";
        if ($stock === 'low') $w[] = 'p.stock<=p.low_stock_threshold';
        $where = implode(' AND ', $w); $per = 15; $page = $this->page();
        $total = (int)DB::val("SELECT COUNT(*) FROM products p WHERE $where", $p);
        $rows = DB::all("SELECT p.*, " . discount_sql() . " AS selling_price, " . company_discount() . " AS discount_percent, c.name category_name, (SELECT path FROM product_images i WHERE i.product_id=p.id ORDER BY i.is_primary DESC,i.sort_order,i.id LIMIT 1) image FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $this->render('admin/products/index', ['title' => 'Products', 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'categories' => DB::all('SELECT id,name FROM categories ORDER BY name'), 'f' => compact('q', 'cat', 'st', 'stock')]);
    }
    /** Excel download: ?blank=1 gives an empty template, otherwise every current product (edit & re-upload). */
    public function template(): void {
        if (!Xlsx::available()) { flash('error', 'The PHP "zip" extension is off, so Excel files cannot be created. Enable extension=zip in php.ini.'); redirect('/admin/products/import'); }
        $includeArchived = !empty($_GET['include_archived']);
        $bin = ProductSheet::export(empty($_GET['blank']), $includeArchived);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="products-' . (empty($_GET['blank']) ? 'edit-' . ($includeArchived ? 'all-' : '') . date('Ymd-His') : 'new-template') . '.xlsx"');
        header('Content-Length: ' . strlen($bin)); header('Cache-Control: no-store');
        echo $bin; exit;
    }
    public function importForm(): void {
        $this->render('admin/products/import', [
            'title' => 'Excel import / export',
            'result' => null,
            'preview' => null,
            'confirmToken' => null,
            'error' => null,
            'zip' => Xlsx::available(),
            'stockMode' => 'replace',
            'createCategories' => false,
        ]);
    }
    public function importSubmit(): void {
        $this->post();
        $action = (string)($_POST['action'] ?? '');
        $stockModeValue = $_POST['stock_mode'] ?? 'replace';
        $stockMode = is_string($stockModeValue) && in_array($stockModeValue, ['replace', 'add'], true) ? $stockModeValue : 'replace';
        $createCategories = !empty($_POST['create_categories']);
        $result = null;
        $preview = null;
        $confirmToken = null;
        $error = null;

        if ($action === 'confirm') {
            $confirmToken = (string)($_POST['confirm_token'] ?? '');
            $entry = ProductImportStash::get($confirmToken);
            if (!$entry) {
                $error = 'Preview session expired or not found. Please upload the file again.';
            } else {
                try {
                    @set_time_limit(120);
                    $result = ProductSheet::import($entry['file'], $entry['name'], $createCategories, $stockMode);
                    ProductImportStash::clear($confirmToken);
                    $confirmToken = null;
                } catch (\RuntimeException $e) {
                    $error = $e->getMessage();
                }
                if ($result) {
                    log_error(sprintf('product import confirmed by %s: +%d ~%d =%d !%d', $this->admin['email'] ?? '?', $result['added'], $result['updated'], $result['unchanged'], count($result['errors'])));
                }
            }
        } elseif ($action === 'cancel_preview') {
            $confirmToken = (string)($_POST['confirm_token'] ?? '');
            ProductImportStash::clear($confirmToken);
            flash('info', 'Import preview cancelled.');
            redirect('/admin/products/import');
            return;
        } else {
            $f = $_FILES['sheet'] ?? null;
            if (!$f || ($f['error'] ?? 4) !== UPLOAD_ERR_OK) {
                $error = ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE || !$f ? 'Choose the Excel file first.' : 'Upload failed (file too large for the server?).';
            } elseif ($f['size'] > 8 * 1024 * 1024) {
                $error = 'File is too large (max 8 MB).';
            } elseif (!is_uploaded_file($f['tmp_name'])) {
                $error = 'Invalid upload.';
            } else {
                if ($action === 'preview') {
                    $staged = ProductImportStash::stage($f['tmp_name'], (string)$f['name'], compact('stockMode', 'createCategories'));
                    if ($staged['error'] || !$staged['token']) {
                        $error = $staged['error'] ?? 'Could not stage file for preview.';
                    } else {
                        $confirmToken = $staged['token'];
                        $stashed = ProductImportStash::get($confirmToken);
                        try {
                            @set_time_limit(120);
                            $preview = ProductSheet::preview($stashed['file'], (string)$f['name'], $createCategories, $stockMode);
                        } catch (\RuntimeException $e) {
                            $error = $e->getMessage();
                            ProductImportStash::clear($confirmToken);
                            $confirmToken = null;
                        }
                    }
                } else {
                    // Direct import
                    try {
                        @set_time_limit(120);
                        $result = ProductSheet::import($f['tmp_name'], (string)$f['name'], $createCategories, $stockMode);
                    } catch (\RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                    if ($result) {
                        log_error(sprintf('product import by %s: +%d ~%d =%d !%d', $this->admin['email'] ?? '?', $result['added'], $result['updated'], $result['unchanged'], count($result['errors'])));
                    }
                }
            }
        }
        $this->render('admin/products/import', [
            'title' => 'Excel import / export',
            'result' => $result,
            'preview' => $preview,
            'confirmToken' => $confirmToken,
            'error' => $error,
            'zip' => Xlsx::available(),
            'stockMode' => $stockMode,
            'createCategories' => $createCategories,
        ]);
    }
    public function show(string $id): void {
        $p = DB::one('SELECT p.*, ' . discount_sql() . ' AS selling_price, ' . company_discount() . ' AS discount_percent, c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?', [(int)$id]);
        if (!$p) redirect('/admin/products');
        $this->render('admin/products/view', ['title' => $p['name'], 'p' => $p, 'images' => DB::all('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order, id', [(int)$id]),
            'sold' => (int)DB::val('SELECT COALESCE(SUM(quantity),0) FROM order_items WHERE product_id=?', [(int)$id])]);
    }
    public function form(string $id = ''): void {
        $p = $id !== '' ? DB::one('SELECT * FROM products WHERE id=?', [(int)$id]) : null;
        if ($id !== '' && !$p) redirect('/admin/products');
        $pendingTokenValue = $_GET['pending_upload_token'] ?? '';
        $pendingToken = is_string($pendingTokenValue) ? $pendingTokenValue : '';
        $pendingUploads = ProductUploadStash::get($pendingToken);
        if (!$pendingUploads['images'] && !$pendingUploads['video']) $pendingToken = '';
        $cats = DB::all('SELECT id,name FROM categories ORDER BY name');
        $this->render('admin/products/form', ['title' => $p ? 'Edit product' : 'Add product', 'p' => $p, 'categories' => $cats, 'skuPreview' => $p ? [] : $this->skuPreview($cats), 'discountPct' => company_discount(),
            'images' => $p ? DB::all('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order, id', [(int)$id]) : [], 'errors' => [],
            'pendingUploadToken' => $pendingToken, 'pendingUploads' => $pendingUploads, 'uploadErrors' => []]);
    }
    public function save(): void {
        // The files together are bigger than post_max_size: PHP then throws away the WHOLE request ($_POST and $_FILES are empty). Without this
        // check the admin would see "session expired" or a blank page; now there is a clear message and the form opens again.
        if (is_post() && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $lim = MediaService::limits();
            flash('error', 'The files you chose are too big to upload together (' . MediaService::humanSize((int)$_SERVER['CONTENT_LENGTH']) . ' sent, the server accepts at most ' . ($lim['post'] === PHP_INT_MAX ? 'a limited amount' : MediaService::humanSize($lim['post'])) . ' per save). Nothing was saved. Use images of max ' . MediaService::humanSize(MediaService::MAX_BYTES) . ' each, fewer files at once, or a smaller video, then try again.');
            $ref = (string)parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
            redirect(str_starts_with($ref, base_path() . '/admin/products') ? $ref : '/admin/products/create');
        }
        $this->post();
        $requestedId = (int)($_POST['id'] ?? 0);
        $pendingTokenValue = $_POST['pending_upload_token'] ?? '';
        $pendingTokenInput = is_string($pendingTokenValue) ? $pendingTokenValue : '';
        if (!empty($_POST['drop_pending']) && is_array($_POST['drop_pending'])) ProductUploadStash::drop($pendingTokenInput, array_values(array_filter($_POST['drop_pending'], 'is_string')));   // kept images the admin ticked "Remove" on
        $stage = ProductUploadStash::stage($pendingTokenInput, $this->uploadedImages(), $this->uploadedVideo());
        $pendingToken = $stage['token'];
        $pendingUploads = ProductUploadStash::get($pendingToken);
        $id = (int)($_POST['id'] ?? 0);
        $existing = $id ? DB::one('SELECT * FROM products WHERE id=?', [$id]) : null;
        $d = ['name' => trim((string)($_POST['name'] ?? '')), 'name_ta' => mb_substr(trim((string)($_POST['name_ta'] ?? '')), 0, 190) ?: null, 'category_id' => (int)($_POST['category_id'] ?? 0),
            'description' => trim((string)($_POST['description'] ?? '')), 'safety_notes' => trim((string)($_POST['safety_notes'] ?? '')), 'aliases' => trim((string)($_POST['aliases'] ?? '')),
            'original_price' => round((float)($_POST['original_price'] ?? 0), 2),
            'stock' => (int)($_POST['stock'] ?? 0), 'low_stock_threshold' => max(0, (int)($_POST['low_stock_threshold'] ?? 10)),
            'is_featured' => empty($_POST['is_featured']) ? 0 : 1, 'is_popular' => empty($_POST['is_popular']) ? 0 : 1, 'status' => (string)($_POST['status'] ?? 'active')];
        $err = [];
        // SKU is never typed by the admin: it is generated by SkuService inside the save transaction (new product) and never changes afterwards.
        if (mb_strlen($d['name']) < 2 || mb_strlen($d['name']) > 190) $err['name'] = 'Name is required (max 190 chars).';
        if (!DB::val('SELECT 1 FROM categories WHERE id=?', [$d['category_id']])) $err['category_id'] = 'Select a category.';
        if ($d['original_price'] <= 0 || $d['original_price'] > 9999999) $err['original_price'] = 'Enter a valid original price.';
        if ($d['stock'] < 0 || $d['stock'] > 10000000) $err['stock'] = 'Invalid stock.';
        if (!in_array($d['status'], ['active', 'inactive', 'archived'], true)) $d['status'] = 'inactive';
        if ($err || $stage['errors']) {   // a rejected file also stops the save: the form comes back with the message, the good files stay attached (pending_upload_token)
            $cats = DB::all('SELECT id,name FROM categories ORDER BY name');
            $this->render('admin/products/form', ['title' => $id ? 'Edit product' : 'Add product', 'p' => $d + ($existing ?? []) + ['id' => $id], 'errors' => $err, 'categories' => $cats, 'skuPreview' => $id ? [] : $this->skuPreview($cats), 'discountPct' => company_discount(),
                'images' => $id ? DB::all('SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC, sort_order, id', [$id]) : [],
                'pendingUploadToken' => $pendingToken, 'pendingUploads' => $pendingUploads, 'uploadErrors' => $stage['errors']]); return;
        }
        // Company-wide discount: original price is stored as entered; these two columns are only a cache (all reads recompute live).
        $d['selling_price'] = discounted_price($d['original_price']); $d['discount_percent'] = company_discount();
        $pdo = DB::pdo();
        try {
            $pdo->beginTransaction();
            if ($id) { DB::update('products', $d, 'id=?', [$id]); }   // SKU and slug are not in $d: they never change after creation
            else {
                // new product: the SKU is reserved while the category row is locked; the UNIQUE index is the last line of defence (retry once or twice)
                for ($try = 0; ; $try++) {
                    $d['sku'] = SkuService::next($d['category_id']);
                    $d['slug'] = slugify($d['name']) . '-' . strtolower($d['sku']);
                    try { $id = DB::insert('products', $d); break; }
                    catch (\PDOException $e) { if ($try >= 3 || (int)$e->getCode() !== 23000) throw $e; }
                }
            }
            $errs = $stage['errors'];
            // product video: replace or remove
            $oldVideo = $id ? (string)DB::val('SELECT video_path FROM products WHERE id=?', [$id]) : '';
            $newVideo = null; $removeVideo = !empty($_POST['remove_video']);
            if (!empty($pendingUploads['video'])) {
                $vr = MediaService::fromVideoBytes((string)@file_get_contents($pendingUploads['video']['path']), $pendingUploads['video']['name']);
                if ($vr['ok']) { $newVideo = $vr['path']; DB::exec('UPDATE products SET video_path=? WHERE id=?', [$newVideo, $id]); }
                else $errs[] = 'Video: ' . $vr['message'];
            } elseif ($removeVideo && $oldVideo !== '') { DB::exec('UPDATE products SET video_path=NULL WHERE id=?', [$id]); }
            foreach ($this->ids('remove_images') as $rid) DB::exec('DELETE FROM product_images WHERE id=? AND product_id=?', [$rid, $id]);
            $order = (int)DB::val('SELECT COALESCE(MAX(sort_order),0) FROM product_images WHERE product_id=?', [$id]);
            foreach ($pendingUploads['images'] as $image) {
                $res = MediaService::fromBytes((string)@file_get_contents($image['path']), $image['name']);
                if ($res['ok']) DB::insert('product_images', ['product_id' => $id, 'path' => $res['media']['path'], 'sort_order' => ++$order]);
                else $errs[] = $image['name'] . ': ' . $res['message'];
            }
            foreach ((array)($_POST['library_images'] ?? []) as $lp) {
                $path = MediaService::validPath(is_string($lp) ? $lp : '');
                if ($path && !DB::val('SELECT 1 FROM product_images WHERE product_id=? AND path=?', [$id, $path])) DB::insert('product_images', ['product_id' => $id, 'path' => $path, 'sort_order' => ++$order]);
            }
            $primary = (int)($_POST['primary_image'] ?? 0);
            if ($primary && DB::val('SELECT 1 FROM product_images WHERE id=? AND product_id=?', [$primary, $id])) {
                DB::exec('UPDATE product_images SET is_primary=0 WHERE product_id=?', [$id]); DB::exec('UPDATE product_images SET is_primary=1 WHERE id=?', [$primary]);
            } elseif (!DB::val('SELECT 1 FROM product_images WHERE product_id=? AND is_primary=1', [$id])) {
                DB::exec('UPDATE product_images SET is_primary=1 WHERE product_id=? ORDER BY sort_order, id LIMIT 1', [$id]);
            }
            $pdo->commit();
            ProductUploadStash::clear($pendingToken);
            if (($newVideo || $removeVideo) && $oldVideo !== '') MediaService::deleteVideo($oldVideo);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('product save: ' . $e->getMessage()); flash('error', 'Could not save the product.');
            $retry = $requestedId ? "/admin/products/$requestedId/edit" : '/admin/products/create';
            if ($pendingToken) $retry .= '?pending_upload_token=' . rawurlencode($pendingToken);
            redirect($retry);
        }
        foreach ($errs as $m) flash('error', $m);
        flash('success', 'Product saved.');
        redirect("/admin/products/$id");
    }
    /** category id => the SKU the next product of that category will get (a hint on the form; the real SKU is reserved on save). */
    private function skuPreview(array $cats): array {
        $out = []; foreach ($cats as $c) { try { $out[(int)$c['id']] = SkuService::peek((int)$c['id']); } catch (\Throwable $e) { $out[(int)$c['id']] = ''; } }
        return $out;
    }
    private function uploadedImages(): array {
        $files = $_FILES['images'] ?? [];
        if (!is_array($files['name'] ?? null)) return [];
        $out = [];
        foreach ($files['name'] as $i => $name) {
            $out[] = ['name' => (string)$name, 'tmp_name' => (string)($files['tmp_name'][$i] ?? ''), 'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE)];
        }
        return $out;
    }
    private function uploadedVideo(): ?array {
        $file = $_FILES['video'] ?? null;
        if (!$file || !is_array($file)) return null;
        return ['name' => (string)($file['name'] ?? ''), 'tmp_name' => (string)($file['tmp_name'] ?? ''), 'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }
    public function archive(string $id): void { $this->post(); DB::exec("UPDATE products SET status='archived' WHERE id=?", [(int)$id]); flash('success', 'Product archived (hidden from customers).'); redirect('/admin/products'); }
    public function restore(string $id): void { $this->post(); DB::exec("UPDATE products SET status='inactive' WHERE id=? AND status='archived'", [(int)$id]); flash('success', 'Product restored as inactive.'); redirect('/admin/products?status=archived'); }
    public function delete(string $id): void {
        $this->post();
        if ((int)DB::val('SELECT COUNT(*) FROM order_items WHERE product_id=?', [(int)$id]) > 0) { DB::exec("UPDATE products SET status='archived' WHERE id=?", [(int)$id]); flash('info', 'This product has order history, so it was archived instead of deleted.'); }
        else { MediaService::deleteVideo((string)DB::val('SELECT video_path FROM products WHERE id=?', [(int)$id])); DB::exec('DELETE FROM products WHERE id=?', [(int)$id]); flash('success', 'Product deleted.'); }
        redirect('/admin/products');
    }
}
