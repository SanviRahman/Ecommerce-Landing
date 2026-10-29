"""P55 static regression contracts. Run against the supplied Laravel source.

These source checks do NOT substitute for real MySQL checkout/inventory tests.
"""
from pathlib import Path
import re
import sys
import unittest

ROOT = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[2]
sys.argv = sys.argv[:1]

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

class P55Contracts(unittest.TestCase):
    def test_fk_source_and_idempotency_tombstone(self):
        old = read('database/migrations/2026_10_24_210005_p02_create_inventory_ledger_and_checkout_requests.php')
        self.assertIn("->constrained('orders')->restrictOnDelete()", old)
        s = read('app/Services/Orders/AuditedOrderTrashPurger.php')
        self.assertIn("'status' => 'purged'", s)
        self.assertIn("'order_id' => null", s)
        self.assertIn("DB::transaction(", s)
        self.assertIn("Order::onlyTrashed()", s)
        self.assertIn("->lockForUpdate()", s)
        self.assertNotIn("FOREIGN_KEY_CHECKS", s)
        self.assertNotIn("DB::table('checkout_requests')->delete()", s)
    def test_stock_remains_and_audit(self):
        s = read('app/Services/Orders/AuditedOrderTrashPurger.php')
        self.assertIn("DB::table('order_purge_audits')->insert", s)
        self.assertIn("'movement_ids' =>", s)
        self.assertIn("'status_history' =>", s)
        self.assertIn("'order_item_id' => null", s)
        self.assertNotIn("->increment('stock'", s)
        self.assertNotIn("->decrement('stock'", s)
        self.assertIn('PAYMENT_STATUS_COLLECTED', s)
        self.assertIn('external_order_syncs', s)
        self.assertIn('inventory_allocated_quantity', s)
    def test_admin_only_and_all_delete_actions_use_transaction_service(self):
        s=read('app/Http/Controllers/Admin/OrderController.php')
        for action in ('forceDelete($id, AuditedOrderTrashPurger $purger)',
                       'emptyTrash(AuditedOrderTrashPurger $purger)',
                       "if ($action === 'force_delete')"):
            self.assertIn(action, s)
        self.assertEqual(len(re.findall(r'\$purger->purge\(', s)), 2)
        self.assertNotIn('Order::onlyTrashed()->forceDelete()', s)
        self.assertNotIn("Order::onlyTrashed()->whereIn('id', $ids)->forceDelete()",s)
        self.assertIn('$this->adminOnly();',s[s.index('public function forceDelete'):s.index('public function multipleAction')])
    def test_audit_migration_is_immutable_and_no_fk_disable(self):
        s=read('database/migrations/2026_10_29_100001_create_order_purge_audits.php')
        self.assertIn("Schema::create('order_purge_audits'",s)
        self.assertIn("$table->json('audit_snapshot')",s)
        self.assertIn('Cannot roll back order_purge_audits',s)
    def test_wa_is_external_and_app_deep_link_without_dialog_interception(self):
        blade=read('resources/views/storefront/partials/contact-widget.blade.php')
        js=read('public/frontend/js/storefront-p52.js')
        self.assertIn('https://api.whatsapp.com/send?phone=',blade)
        self.assertIn('whatsapp://send?phone=',blade)
        self.assertIn('target="_blank" rel="noopener noreferrer"',blade)
        self.assertIn('data-p55-whatsapp-direct',blade)
        self.assertNotIn('data-p52-whatsapp-dialog',blade)
        self.assertNotIn('dialog.showModal()',js)
        self.assertNotIn('event.preventDefault()',js)
        self.assertIn("link.removeAttribute('target')",js)
    def test_named_homepage_path_without_moving_other_collections(self):
        s=read('app/MediaLibrary/HomepageMediaPathGenerator.php')
        self.assertIn("'storefront_hero_image' => 'hero-banner'",s)
        self.assertIn("'storefront_logo' => 'branding/logo'",s)
        self.assertIn('homepage_named_path',s)
        self.assertIn("'/media-' . $id",s)
        cfg=read('config/media-library.php')
        self.assertIn('SiteSetting::class => HomepageMediaPathGenerator::class',cfg)
        self.assertIn('Product::class => ProductMediaPathGenerator::class',cfg)
        self.assertIn('Campaign::class => LandingMediaPathGenerator::class',cfg)
        controller=read('app/Http/Controllers/Admin/HomepageBuilderController.php')
        self.assertIn("'homepage_section_key' => $sectionKey",controller)
    def test_media_organizer_is_opt_in_safe_and_keeps_numeric_files(self):
        svc=read('app/Services/HomepageMediaSectionOrganizerService.php')
        cmd=read('app/Console/Commands/OrganizeHomepageMedia.php')
        self.assertIn('if (! $apply)',svc)
        self.assertIn("hash_file('sha256'",svc)
        self.assertIn('->setCustomProperty(',svc)
        self.assertNotIn('File::deleteDirectory',svc)
        self.assertNotIn('unlink(',svc)
        self.assertIn('{--apply',cmd)
        self.assertIn("app()->environment('local', 'testing')",cmd)
    def test_homepage_media_ui_is_section_filtered_and_no_new_migration(self):
        c=read('app/Http/Controllers/Admin/MediaManagementController.php')
        nav=read('config/adminlte.php')
        routes=read('routes/admin.php')
        self.assertIn("'homepage.hero'",c)
        self.assertIn("'homepage.content'",c)
        self.assertIn("'homepage.branding'",c)
        self.assertIn("'homepage_section_items as item'",c)
        self.assertIn("'text' => 'Homepage Media'",nav)
        self.assertIn("'text' => 'Image & Text'",nav)
        self.assertIn("Route::get('/homepage/{section}', 'homepageSection')",routes)
        self.assertIn('homepageMediaSectionLabels',read('resources/views/admin/media-management/partials/table.blade.php'))

if __name__=='__main__':
    unittest.main(verbosity=2)
