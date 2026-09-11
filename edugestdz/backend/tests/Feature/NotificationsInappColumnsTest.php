<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationsInappColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_notifications_inapp_existe(): void
    {
        $this->assertTrue(Schema::hasTable('notifications_inapp'));
    }

    public function test_colonne_action_url_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('notifications_inapp', 'action_url'));
    }

    public function test_colonne_icone_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('notifications_inapp', 'icone'));
    }

    public function test_colonne_lien_existe_toujours(): void
    {
        $this->assertTrue(Schema::hasColumn('notifications_inapp', 'lien'));
    }

    public function test_colonne_action_url_est_nullable(): void
    {
        $columns = Schema::getColumns('notifications_inapp');
        $actionUrl = collect($columns)->firstWhere('name', 'action_url');

        $this->assertNotNull($actionUrl);
        $this->assertTrue($actionUrl['nullable']);
    }
}
