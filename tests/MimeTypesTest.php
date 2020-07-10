<?php

namespace com\github\adangel\chunkphp;

use PHPUnit\Framework\TestCase;

class MimeTypesTest extends TestCase {
    public function testTypesVirtual() {
        $this->assertEquals('application/zip', MimeTypes::determineVirtual('file.zip'));
        $this->assertEquals('application/x-gtar-compressed', MimeTypes::determineVirtual('path/file.tar.gz'));
        $this->assertEquals('application/x-tar', MimeTypes::determineVirtual('file.tar'));
        $this->assertEquals('text/html', MimeTypes::determineVirtual('index.html'));
        $this->assertEquals('text/css', MimeTypes::determineVirtual('file.css'));
        $this->assertEquals('application/javascript', MimeTypes::determineVirtual('file.js'));
        $this->assertEquals('image/png', MimeTypes::determineVirtual('path/to/image.png'));
        $this->assertEquals('image/jpeg', MimeTypes::determineVirtual('/absolute/image.jpg'));
        $this->assertEquals('image/jpeg', MimeTypes::determineVirtual('image.jpeg'));
        $this->assertEquals('image/gif', MimeTypes::determineVirtual('image.gif'));
        $this->assertEquals('text/plain', MimeTypes::determineVirtual('test.txt'));
        $this->assertEquals('application/octet-stream', MimeTypes::determineVirtual('file.unknown'));
        $this->assertEquals('application/octet-stream', MimeTypes::determineVirtual('file/noextension'));
    }

    public function testTypesReal() {
        $path = __DIR__ . '/storage-test-data/joe/5edaecac-6ac4-41c9-980d-e1e109c6b105';
        $this->assertEquals('text/plain; charset=us-ascii', MimeTypes::determineReal($path));

        $path = __DIR__ . '/storage-test-data/joe/9ef4d6c0-543b-4824-a05a-4b9a44b3fceb.txt';
        $this->assertEquals('text/plain; charset=us-ascii', MimeTypes::determineReal($path));

        $path = __DIR__ . '/storage-test-data/joe/17621e7d-6e8d-449f-aa48-e81728994afc.tar.gz';
        $this->assertEquals('application/x-gtar-compressed', MimeTypes::determineReal($path));
    }
}
