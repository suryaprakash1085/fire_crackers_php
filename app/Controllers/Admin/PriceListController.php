<?php
namespace App\Controllers\Admin;

use App\Models\Setting;
use App\Services\PriceList;

/** Price list PDF: upload / replace / remove one, or let the site generate it from the active products. Visitors download it from /price-list. */
final class PriceListController extends AdminController {
    protected string $minRole = 'staff';
    public function index(): void { $this->render('admin/price-list', ['title' => 'Price List']); }

    public function save(): void {
        $this->post();
        $mode = $this->input('mode', PriceList::mode()); if (!in_array($mode, ['uploaded', 'auto'], true)) $mode = 'uploaded';
        $oldPath = (string)setting('pricelist_path', ''); $kv = ['pricelist_mode' => $mode]; $newPath = $oldPath; $msg = 'Price list settings saved.';
        if (!empty($_FILES['pricelist']['name'])) {
            $r = PriceList::store($_FILES['pricelist']);
            if (!$r['ok']) { flash('error', $r['message']); redirect('/admin/price-list'); }
            $newPath = (string)$r['path'];
            $kv += ['pricelist_path' => $newPath, 'pricelist_name' => (string)$r['name'], 'pricelist_updated_at' => now()];
            $msg = $oldPath !== '' ? 'Price list replaced.' : 'Price list uploaded.';
        } elseif ($mode === 'uploaded' && PriceList::uploadedFile() === null) {
            flash('error', 'Upload a PDF first, or choose “Generate automatically”.'); redirect('/admin/price-list');
        }
        if ($mode === 'auto' && !PriceList::hasProducts()) $msg .= ' Note: there are no active products yet, so the Download button stays hidden until you add some.';
        Setting::setMany($kv);
        if ($newPath !== $oldPath && $oldPath !== '') PriceList::delete($oldPath);   // the replaced file is removed only after the new settings are saved
        flash('success', $msg); redirect('/admin/price-list');
    }

    public function remove(): void {
        $this->post();
        $old = (string)setting('pricelist_path', '');
        if ($old === '') { flash('info', 'There is no uploaded price list to remove.'); redirect('/admin/price-list'); }
        Setting::setMany(['pricelist_path' => '', 'pricelist_name' => '', 'pricelist_updated_at' => '']);
        PriceList::delete($old);
        flash('success', 'Uploaded price list removed.' . (PriceList::mode() === 'uploaded' ? ' The Download button is hidden until you upload a new PDF or switch to automatic generation.' : ''));
        redirect('/admin/price-list');
    }

    /** Opens the PDF in the browser. ?src=uploaded | auto previews that source; without it, whatever visitors currently get. */
    public function preview(): void {
        $src = $this->input('src');
        if (!PriceList::send(true, $src === '' ? null : $src)) { flash('error', 'Nothing to preview yet.'); redirect('/admin/price-list'); }
    }
}
