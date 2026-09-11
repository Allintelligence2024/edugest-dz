<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class BibliothequeTest extends TestCase
{
    public function test_bibliotheque_non_implementee(): void
    {
        $this->markTestSkipped('Module bibliotheque pas encore implémenté (pas de migrations ni routes)');
    }
}
