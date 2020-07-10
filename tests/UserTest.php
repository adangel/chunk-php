<?php

namespace com\github\adangel\chunkphp;

use Exception;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase {

    public function testValidUser() {
        $this->assertEquals('joe', new User('joe'));
        $this->assertEquals('jon-doe', new User('jon-doe'));
        $this->assertEquals('jon_doe', new User('jon_doe'));
        $this->assertEquals('123', new User('123'));
        $this->assertEquals('foobarlong', new User('foobarlong'));
    }

    public function testInvalidUser() {
        $this->assertExceptionInvalidUser('../../etc');
        $this->assertExceptionInvalidUser('jondoe!');
        $this->assertExceptionInvalidUser('/joe');
        $this->assertExceptionInvalidUser('jon doe');
    }

    private function assertExceptionInvalidUser($user) {
        try {
            new User($user);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('Invalid username!', $e->getMessage());
        }
    }
}
