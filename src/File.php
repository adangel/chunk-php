<?php
namespace com\github\adangel\chunkphp;

use finfo;
use ZipArchive;

class File {
    private $realPath;
    private $user;
    private $uuid;
    private $url;

    function __construct($realPath, $baseurl, $user, $uuid) {
        $this->realPath = $realPath;
        $this->user = $user;
        $this->uuid = $uuid;
        $this->url = $baseurl . '/' . $user . '/' . $uuid;

        $dir = dirname($this->realPath);
        if (!file_exists($dir)) {
            mkdir($dir);
        }
    }

    function __toString() : string {
        return $this->realPath;
    }

    function getRealPath() : string {
        return $this->realPath;
    }

    function getName() : string {
    }

    function getUuid() : string {
        return $this->uuid;
    }

    function getType($pathInArchive = NULL) : string {
        $finfo = new finfo(FILEINFO_MIME);
        if (!isset($pathInArchive)) {
            return $finfo->file($this->realPath);
        }

        if ($this->isZip()) {
            return $this->getZipEntryType($finfo, $pathInArchive);
        }

        return 'unknown type';
    }

    function getSize($pathInArchive = NULL) : int {
        if (!isset($pathInArchive)) {
            return filesize($this->realPath);
        }

        if ($this->isZip()) {
            $entry = $this->getZipEntry($pathInArchive);
            if ($entry !== FALSE) {
                return $entry['size'];
            }
        }

        return FALSE;
    }

    function getUrl() : string {
        return $this->url;
    }

    function hasContent($pathInArchive = NULL) : bool {
        if (!file_exists($this->realPath)) {
            return FALSE;
        }

        if (!isset($pathInArchive)) {
            return TRUE;
        }

        if ($this->isZip()) {
            $entry = $this->getZipEntry($pathInArchive);
            if ($entry === FALSE) {
                return FALSE;
            }
            return TRUE;
        } else if ($this->isTar()) {
            throw new Exception("Couldn't open tar file {$this->realPath}");
        }
        return FALSE;
    }

    private function isZip() : bool {
        return strpos($this->getType(), 'application/zip') === 0;
    }

    /** @return array|false */
    private function getZipEntry($pathInArchive) {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === '') {
            return FALSE;
        }

        $zip = new ZipArchive;
        $res = $zip->open($this->realPath, ZipArchive::RDONLY);
        if ($res === FALSE) {
            throw new Exception("Couldn't open zip file {$this->realPath}: $res");
        }
        $entry = $zip->statName($path);
        $zip->close();
        return $entry;
    }

    private static function sanitizeArchivePath($path) {
        return trim($path, '/');
    }

    private function getZipEntryType($finfo, $pathInArchive) : string {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === '') {
            throw new Exception("Invalid entry $pathInArchive");
        }

        $zip = new ZipArchive;
        $res = $zip->open($this->realPath, ZipArchive::RDONLY);
        if ($res === FALSE) {
            throw new Exception("Couldn't open zip file {$this->realPath}: $res");
        }
        $fp = $zip->getStream($path);
        if ($fp === FALSE) {
            throw new Exception("Entry $path in file {$this->realPath} not found");
        }
        $contents = fread($fp, 8192);
        $mimetype = $finfo->buffer($contents);
        fclose($fp);
        $zip->close();

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'css') {
            $mimetype = 'text/css';
        } else if ($extension === 'js') {
            $mimetype = 'application/javascript';
        }

        return $mimetype;
    }

    private function sendZipEntry($stream, $pathInArchive) : void {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === '') {
            throw new Exception("Invalid entry $pathInArchive");
        }

        $zip = new ZipArchive;
        $res = $zip->open($this->realPath, ZipArchive::RDONLY);
        if ($res === FALSE) {
            throw new Exception("Couldn't open zip file {$this->realPath}: $res");
        }
        $fp = $zip->getStream($path);
        if ($fp === FALSE) {
            throw new Exception("Entry $path in file {$this->realPath} not found");
        }
        while (!feof($fp)) {
            $contents = fread($fp, 8192);
            fwrite($stream, $contents);
        }
        fclose($fp);
        $zip->close();
    }

    private function isTar() : bool {
        return strpos($this->getType(), 'application/x-tar') === 0;
    }

    /**
     * Send the content of the referenced file to the given stream.
     * If no stream is given, uses php://output by default.
     */
    function send($stream = NULL) : void {
        if ($stream === NULL) {
            $stream = fopen('php://output', 'w');
        }
        $handle = fopen($this->realPath, 'r');
        while (!feof($handle)) {
            $contents = fread($handle, 8192);
            fwrite($stream, $contents);
        }
        fclose($handle);
    }

    function sendArchivePath($pathInArchive, $stream = NULL) : void {
        if ($stream === NULL) {
            $stream = fopen('php://output', 'w');
        }
        if ($this->isZip()) {
            $this->sendZipEntry($stream, $pathInArchive);
            return;
        }
        throw new Exception("File {$this->realPath} is not an archive");
    }

    /**
     * Reads from the given stream and stores the content into the
     * file. If no stream is given, uses php://input by default.
     */
    function store($stream = NULL) : void {
        if ($stream == NULL) {
            $stream = fopen('php://input', 'r');
        }
        $handle = fopen($this->realPath, "w");
        while ($data = fread($stream, 8192)) {
            fwrite($handle, $data);
        }
        fclose($handle);
    }
}
