<?php

namespace com\github\adangel\chunkphp;

use Exception;
use PHPUnit\Framework\TestCase;

class StorageTest extends TestCase {
    private $basepath = __DIR__ . '/storage-test-data';
    private $storage;

    public function setUp() : void {
        $this->storage = new Storage('https://chunk.example.com', $this->basepath);
    }

    public function testFindFileExisting() {
        $file = $this->storage->findFile('joe', '5edaecac-6ac4-41c9-980d-e1e109c6b105');
        $this->assertNotNull($file);
        $this->assertEquals($this->basepath . '/joe/5edaecac-6ac4-41c9-980d-e1e109c6b105', $file->getRealPath());
        $this->assertEquals(57, $file->getSize());
        $this->assertEquals('text/plain; charset=us-ascii', $file->getType());
        $this->assertEquals('https://chunk.example.com/joe/5edaecac-6ac4-41c9-980d-e1e109c6b105', $file->getUrl());
        $this->assertTrue($file->hasContent());

        $stream = fopen('php://memory', 'rw');
        $file->send($stream);
        rewind($stream);
        $this->assertEquals('Test file with uuid 5edaecac-6ac4-41c9-980d-e1e109c6b105'. PHP_EOL, stream_get_contents($stream));
    }

    public function testFindFileExistingWithExtension() {
        $file = $this->storage->findFile('joe', '9ef4d6c0-543b-4824-a05a-4b9a44b3fceb');
        $this->assertNotNull($file);
        $this->assertEquals($this->basepath . '/joe/9ef4d6c0-543b-4824-a05a-4b9a44b3fceb.txt', $file->getRealPath());
        $this->assertEquals(78, $file->getSize());

        $this->expectOutputString('Test file for uuid 9ef4d6c0-543b-4824-a05a-4b9a44b3fceb with extension "txt".' . PHP_EOL);
        $file->send();
    }

    public function testFindFileNotExisting() {
        $info = $this->storage->findFile('bar', 'd9fbb032-0dde-4df8-90db-b5ce4aa80ac6');
        $this->assertNull($info);
    }

    public function testCreateNewFile() {
        $file = $this->storage->createFile('joe');
        $this->assertFalse($file->hasContent());
        $this->assertFileDoesNotExist($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith($file->getUuid(), $file->getRealPath());
    }

    public function testCreateNewFileWithExtension() {
        $file = $this->storage->createFile('joe', 'original-file-name.zip');
        $this->assertFalse($file->hasContent());
        $this->assertFileDoesNotExist($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith('.zip', $file->getRealPath());
    }

    public function testCreateNewFileWithExtension2() {
        $file = $this->storage->createFile('joe', '/joe/original-file-name.zip');
        $this->assertFalse($file->hasContent());
        $this->assertFileDoesNotExist($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith('.zip', $file->getRealPath());
    }

    public function testCreateAndStoreNewFile() {
        $stream = fopen('php://memory', 'rw');
        fputs($stream, 'test test');
        rewind($stream);

        $file = $this->storage->createFile('joe');
        $this->assertFileDoesNotExist($file->getRealPath());
        $file->store($stream);
        $this->assertFileExists($file->getRealPath());
        $this->assertStringEqualsFile($file->getRealPath(), 'test test');
        $this->assertEquals(9, $file->getSize());
        $this->assertEquals('text/plain; charset=us-ascii', $file->getType());

        unlink($file->getRealPath());
    }

    public function testCreateNewFileForNewUser() {
        $file = $this->storage->createFile('bar');
        $this->assertFalse($file->hasContent());
        $this->assertFileDoesNotExist($file->getRealPath());
        $this->assertDirectoryExists($this->basepath . '/bar/');

        rmdir($this->basepath . '/bar/');
    }

    public function testInvalidUsername() {
        try {
            $this->storage->findFile('joe invalid user', '5edaecac-6ac4-41c9-980d-e1e109c6b105');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('Invalid username!', $e->getMessage());
        }
    }

    public function testInvalidUuid() {
        try {
            $this->storage->findFile('joe', 'a');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('Invalid uuid!', $e->getMessage());
        }
    }

    public function testCreateWithInvalidUsername() {
        try {
            $this->storage->createFile('bar invalid user');
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals('Invalid username!', $e->getMessage());
        }
    }
}
