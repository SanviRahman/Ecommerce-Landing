"""P52 revised, dependency-free Spatie profile contracts.

These are source safety checks, NOT DB-backed profile upload tests.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]

def read(path):
    return (ROOT / path).read_text(encoding="utf-8")

class P52SpatieProfileContract(unittest.TestCase):
    def test_customer_auth_model_uses_spatie(self):
        model = read('app/Models/StorefrontAccount.php')
        self.assertIn('implements HasMedia', model)
        self.assertIn('use InteractsWithMedia;', model)
        self.assertIn("$this->addMediaCollection('profile_photo')", model)
        self.assertIn("->useDisk('public')", model)
        self.assertIn("->singleFile()", model)
        self.assertNotIn("'profile_photo_path'", model)

    def test_photo_handling_uses_media_library_only(self):
        controller = read('app/Http/Controllers/Frontend/StorefrontAccountController.php')
        self.assertIn("Auth::guard('storefront')->user()", controller)
        self.assertIn("'profile_photo' => ['nullable', 'image'", controller)
        self.assertIn("'remove_photo' => ['sometimes', 'boolean']", controller)
        self.assertIn("addMediaFromRequest('profile_photo')", controller)
        self.assertIn("->toMediaCollection('profile_photo', 'public')", controller)
        self.assertIn("clearMediaCollection('profile_photo')", controller)
        self.assertIn('usingFileName(', controller)
        self.assertNotIn('profile_photo_path', controller)
        self.assertNotIn('storefront-avatars', controller)
        self.assertNotIn('File::delete', controller)
        self.assertNotIn('File::ensureDirectoryExists', controller)

    def test_existing_media_table_reused_not_remigrated(self):
        migration = read('database/migrations/2026_10_28_120001_add_storefront_account_profile_fields.php')
        self.assertIn('P02MigrationSafety::assertApprovedP02Up()', migration)
        self.assertIn("Schema::table('storefront_accounts'", migration)
        for field in ('gender', 'date_of_birth', 'division', 'district', 'upazila'):
            self.assertIn("'" + field + "'", migration)
        self.assertNotIn('profile_photo_path', migration)
        self.assertNotIn("Schema::create('media'", migration)
        media_migration = read('database/migrations/2026_05_03_043117_create_media_table.php')
        self.assertIn("Schema::create('media'", media_migration)

    def test_blade_photo_and_actions_are_spatie_driven(self):
        blade = read('resources/views/storefront/account/index.blade.php')
        self.assertIn("getFirstMediaUrl('profile_photo')", blade)
        self.assertIn('enctype="multipart/form-data"', blade)
        self.assertIn('name="profile_photo"', blade)
        self.assertIn('name="remove_photo"', blade)
        self.assertIn('@csrf', blade)
        self.assertIn("@method('PATCH')", blade)
        self.assertNotIn('profile_photo_path', blade)

    def test_no_manual_avatar_upload_paths(self):
        files = [
            'app/Models/StorefrontAccount.php',
            'app/Http/Controllers/Frontend/StorefrontAccountController.php',
            'resources/views/storefront/account/index.blade.php',
            'database/migrations/2026_10_28_120001_add_storefront_account_profile_fields.php',
        ]
        for f in files:
            self.assertNotIn('assets/storefront-avatars', read(f), f)

if __name__ == '__main__':
    unittest.main()
