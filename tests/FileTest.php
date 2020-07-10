<?php

namespace com\github\adangel\chunkphp;

use Exception;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase {
    private $basepath = __DIR__ . '/storage-test-data';

    public function testFindFileZipArchiveContent() {
        $this->assertArchive('joe', '127ceecd-f10b-4d34-88ed-6f0de2133021', '.zip', 9331, 'application/zip; charset=binary');
    }

    public function testFindFileTarArchiveContent() {
        $this->assertArchive('joe', '17621e7d-6e8d-449f-aa48-e81728994afc', '.tar.gz', 8169, 'application/x-gtar-compressed');
    }

    public function testFindFileTarUncompressedArchiveContent() {
        $this->assertArchive('joe', '6ca055e9-a932-4cbe-acef-0434c187bd2c', '.tar', 20480, 'application/x-tar; charset=binary');
    }

    public function testInvalidPathInArchive() {
        $this->assertInvalidPath('127ceecd-f10b-4d34-88ed-6f0de2133021.zip', '../../../bar');
        $this->assertInvalidPath('127ceecd-f10b-4d34-88ed-6f0de2133021.zip', '../../bar/../foo');
    }

    public function testSanitizePath() {
        $this->assertEquals('test.txt', File::sanitizeArchivePath('/test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('//test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('///test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('/./test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('//./test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('//.//test.txt'));
        $this->assertEquals('test.txt', File::sanitizeArchivePath('/bar/../test.txt'));
        $this->assertFalse(File::sanitizeArchivePath('/bar/../../test.txt'));
        $this->assertFalse(File::sanitizeArchivePath('/'));
        $this->assertFalse(File::sanitizeArchivePath(''));
        $this->assertFalse(File::sanitizeArchivePath(NULL));
        $this->assertFalse(File::sanitizeArchivePath('../../../bar'));
        $this->assertFalse(File::sanitizeArchivePath('../../bar/../foo'));
    }

    private function assertInvalidPath($filename, $path) {
        $file = new File("{$this->basepath}/joe/$filename", 'baseurl', 'joe', '123');
        $this->assertFalse($file->hasContent($path));
    }

    private function assertArchive($user, $uuid, $extension, $size, $type) {
        $file = new File("{$this->basepath}/$user/$uuid$extension", 'baseurl', $user, $uuid);
        $this->assertEquals($size, $file->getSize());
        $this->assertEquals($type, $file->getType());
        $this->assertFile($file, 'test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);

        // '/' -> not found
        $this->assertFalse($file->hasContent('/'));

        // '/test.txt' -> works like 'test.txt'
        $this->assertFile($file, '/test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);
        // other variants for test.txt
        $this->assertFile($file, '/foo/bar/../../test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);
        $this->assertFile($file, './test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);
        $this->assertFile($file, './bar/.././test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);
        $this->assertFile($file, './bar/.././/test.txt', 'text/plain', 51, "test file for $uuid" . PHP_EOL);
        $this->assertFile($file, '/style.css', 'text/css', 49, "// $uuid" . PHP_EOL . 'body { }' . PHP_EOL);
        $this->assertFile($file, '/script.js', 'application/javascript', 73,
            "(function() { console.log(\"$uuid\"); })();" . PHP_EOL);
        $this->assertFile($file, '/index.html', 'text/html', 72, "<html><body><h1>$uuid</h1></body></html>" . PHP_EOL);
        $this->assertFile($file, '/sub/folder/foo.html', 'text/html', 67,
            "<html><body>foo $uuid</body></html>" . PHP_EOL);
        $this->assertFile($file, '/image.png', 'image/png', 3220);
        $this->assertFile($file, '/image.jpg', 'image/jpeg', 6096);
    }

    private function assertFile($file, $path, $type, $size, $content = NULL) {
        $this->assertTrue($file->hasContent($path), "Path $path in file $file not found");
        $this->assertEquals($size, $file->getSize($path));
        $this->assertEquals($type, $file->getType($path));

        if (isset($content)) {
            $stream = fopen('php://memory', 'rw');
            $file->sendArchivePath($path, $stream);
            rewind($stream);
            $this->assertEquals($content, stream_get_contents($stream));
        }
    }
}
