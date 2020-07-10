<?php

namespace com\github\adangel\chunkphp;

use Exception;
use PHPUnit\Framework\TestCase;

class UuidTest extends TestCase {
    public function testValidUuids() {
        $this->assertEquals('5edaecac-6ac4-41c9-980d-e1e109c6b105', new Uuid('5edaecac-6ac4-41c9-980d-e1e109c6b105'));
        $this->assertEquals('127ceecd-f10b-4d34-88ed-6f0de2133021', new Uuid('127ceecd-f10b-4d34-88ed-6f0de2133021'));
    }
    public function testInvalidUuids() {
        $this->assertInvalid('127ceecd-f10b-4d34-88ed-6f0de2133021.zip');
        $this->assertInvalid('17621e7d-6e8d-449f-aa48-e81728994afc.tar.gz');
        $this->assertInvalid('a');
        $this->assertInvalid('5Edaecac-6ac4-41c9-980d-e1e109c6b105');
        $this->assertInvalid('5edaecac6ac441c9980de1e109c6b105');
        $this->assertInvalid('5edaecac-6ac4-41c9-980d-e1e109c6b1055edaecac-6ac4-41c9-980d-e1e109c6b105');
    }
    private function assertInvalid($id) {
        try {
            new Uuid($id);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('Invalid uuid!', $e->getMessage());
        }
    }
}
