<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicAppStorePagesTest extends TestCase
{
    public function test_support_page_is_publicly_accessible(): void
    {
        $this->get('/soporte')
            ->assertOk()
            ->assertSee('soporte@hdoc.vet');
    }

    public function test_marketing_page_is_publicly_accessible(): void
    {
        $this->get('/marketing')
            ->assertOk()
            ->assertSee('Tu clínica, organizada en un solo lugar.');
    }

    public function test_copyright_page_is_publicly_accessible(): void
    {
        $this->get('/derechos-de-autor')
            ->assertOk()
            ->assertSee('Todos los derechos reservados.');
    }
}
