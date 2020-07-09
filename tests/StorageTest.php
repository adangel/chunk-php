<?php

namespace com\github\adangel\chunkphp;

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

    public function testFindFileZipArchiveContent() {
        $file = $this->storage->findFile('joe', '127ceecd-f10b-4d34-88ed-6f0de2133021');
        $this->assertEquals(217, $file->getSize());
        $this->assertEquals('application/zip; charset=binary', $file->getType());
        $this->assertFile($file, 'test.txt', 'text/plain; charset=us-ascii', 51,
            'test file for 127ceecd-f10b-4d34-88ed-6f0de2133021'. PHP_EOL);

        // '/' -> not found
        $this->assertFalse($file->hasContent('/'));

        // '/test.txt' -> works like 'test.txt'
        $this->assertFile($file, '/' . $fileInArchive, 'text/plain; charset=us-ascii', 51,
            'test file for 127ceecd-f10b-4d34-88ed-6f0de2133021'. PHP_EOL);
        $this->assertFile($file, '/style.css', 'text/css', 5);
        $this->assertFile($file, '/script.js', 'application/javascript', 5);
        $this->assertFile($file, '/index.html', 'text/html', 5);
        $this->assertFile($file, '/sub/folder/foo.html', 'text/html', 5);
    }

    private function assertFile($file, $path, $type, $size, $content = NULL) {
        $this->assertTrue($file->hasContent($path));
        $this->assertEquals($type, $file->getType($path));
        $this->assertEquals($size, $file->getSize($path));

        if (isset($content)) {
            $stream = fopen('php://memory', 'rw');
            $file->send($stream, $path);
            rewind($stream);
            $this->assertEquals($content, stream_get_contents($stream));
        }
    }

    public function testFindFileTarArchiveContent() {
        $file = $this->storage->findFile('joe', '17621e7d-6e8d-449f-aa48-e81728994afc');
        $this->assertEquals(217, $file->getSize());
        $this->assertEquals('application/x-tar', $file->getType());
        $this->assertFile($file, 'test.txt', 'text/plain; charset=us-ascii', 51,
            'test file for 17621e7d-6e8d-449f-aa48-e81728994afc'. PHP_EOL);

        // '/' -> not found
        $this->assertFalse($file->hasContent('/'));

        // '/test.txt' -> works like 'test.txt'
        $this->assertFile($file, '/' . $fileInArchive, 'text/plain; charset=us-ascii', 51,
            'test file for 17621e7d-6e8d-449f-aa48-e81728994afc'. PHP_EOL);
        $this->assertFile($file, '/style.css', 'text/css', 5);
        $this->assertFile($file, '/script.js', 'application/javascript', 5);
        $this->assertFile($file, '/index.html', 'text/html', 5);
        $this->assertFile($file, '/sub/folder/foo.html', 'text/html', 5);
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
        $this->assertFileNotExists($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith($file->getUuid(), $file->getRealPath());
    }

    public function testCreateNewFileWithExtension() {
        $file = $this->storage->createFile('joe', 'original-file-name.zip');
        $this->assertFalse($file->hasContent());
        $this->assertFileNotExists($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith('.zip', $file->getRealPath());
    }

    public function testCreateNewFileWithExtension2() {
        $file = $this->storage->createFile('joe', '/joe/original-file-name.zip');
        $this->assertFalse($file->hasContent());
        $this->assertFileNotExists($file->getRealPath());
        $this->assertStringStartsWith($this->basepath . '/joe/', $file->getRealPath());
        $this->assertStringEndsWith('.zip', $file->getRealPath());
    }

    public function testCreateAndStoreNewFile() {
        $stream = fopen('php://memory', 'rw');
        fputs($stream, 'test test');
        rewind($stream);

        $file = $this->storage->createFile('joe');
        $this->assertFileNotExists($file->getRealPath());
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
        $this->assertFileNotExists($file->getRealPath());
        $this->assertDirectoryExists($this->basepath . '/bar/');

        rmdir($this->basepath . '/bar/');
    }
}
