<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Env;

final class MediaStorage
{
    public static function local(): bool { return Env::get('APP_ENV') === 'development'; }
    public static function configured(): bool { return self::local() || R2Storage::configured(); }
    public function upload(array $file, string $folder='media'): array
    {
        if (!self::local()) return (new R2Storage())->upload($file,$folder);
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']??'')) throw new \InvalidArgumentException('Choose a valid image to upload.');
        $path=(string)$file['tmp_name']; $size=filesize($path);
        if ($size===false || $size>8*1024*1024) throw new \InvalidArgumentException('Images must be smaller than 8 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        $dimensions=@getimagesize($path);
        if (!isset($extensions[$mime]) || !$dimensions || $dimensions[0]>12000 || $dimensions[1]>12000) throw new \InvalidArgumentException('Choose a valid JPEG, PNG, WebP, or GIF image.');
        $folder=preg_replace('/[^a-z0-9_-]/i','',$folder)?:'media';
        $key='uploads/'.$folder.'/'.date('Y/m').'/'.bin2hex(random_bytes(18)).'.'.$extensions[$mime];
        $target=dirname(__DIR__,2).'/public/'.$key; $directory=dirname($target);
        if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new \RuntimeException('Could not prepare the upload directory.');
        if (!move_uploaded_file($path,$target)) throw new \RuntimeException('Could not save the uploaded image.');
        return ['object_key'=>$key,'url'=>'/'.$key,'mime_type'=>$mime,'size_bytes'=>$size,'width'=>$dimensions[0],'height'=>$dimensions[1]];
    }
    public function delete(array $media): void
    {
        if (str_starts_with((string)$media['url'],'/uploads/')) {
            $root=realpath(dirname(__DIR__,2).'/public/uploads');
            $path=realpath(dirname(__DIR__,2).'/public/'.ltrim((string)$media['object_key'],'/'));
            if ($root===false || $path===false) return;
            if (!str_starts_with(strtolower($path),strtolower($root.DIRECTORY_SEPARATOR)) || !is_file($path)) throw new \RuntimeException('This media path is not a local upload.');
            if (!unlink($path)) throw new \RuntimeException('Could not remove the uploaded file.');
        } elseif (!str_starts_with((string)$media['object_key'],'wordpress-remote/')) {
            (new R2Storage())->delete((string)$media['object_key']);
        }
    }
}
