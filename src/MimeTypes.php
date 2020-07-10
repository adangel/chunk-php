<?php
namespace com\github\adangel\chunkphp;

use finfo;

// https://stackoverflow.com/questions/2455476/determining-a-local-files-mime-type-content-type-with-php
class MimeTypes {
    private static $mimetypes = array(
        'tar' => 'application/x-tar',
        'tar.gz' => 'application/x-gtar-compressed',
        'zip' => 'application/zip',

        'txt' => 'text/plain',

        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',

        'gif' => 'image/gif',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    );

    public static function determineReal($path) : string {
        if (substr($path, -7, 7) === '.tar.gz') {
            return MimeTypes::$mimetypes['tar.gz'];
        }

        $finfo = new finfo(FILEINFO_MIME);
        return $finfo->file($path);
    }

    public static function determineVirtual($path) : string {
        if (substr($path, -7, 7) === '.tar.gz') {
            return MimeTypes::$mimetypes['tar.gz'];
        }

        $path_parts = pathinfo($path);
        if (array_key_exists('extension', $path_parts)
            && array_key_exists($path_parts['extension'], MimeTypes::$mimetypes)) {
            $mime = MimeTypes::$mimetypes[$path_parts['extension']];
        } else {
            $mime = 'application/octet-stream';
        }
        return $mime;
    }
}
