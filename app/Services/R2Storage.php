<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

final class R2Storage
{
    private const ALLOWED = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    private string $endpoint;

    public static function configured(): bool
    {
        foreach (['R2_ACCOUNT_ID','R2_ACCESS_KEY_ID','R2_SECRET_ACCESS_KEY','R2_BUCKET_NAME','R2_PUBLIC_URL'] as $key) {
            if (!Env::get($key)) return false;
        }
        return true;
    }

    public function __construct()
    {
        if (!self::configured()) throw new \RuntimeException('Cloudflare R2 is not configured. Add the five R2 values to .env before uploading images.');
        $this->endpoint = 'https://' . Env::get('R2_ACCOUNT_ID') . '.r2.cloudflarestorage.com';
    }

    public function upload(array $file, string $folder='media'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new \InvalidArgumentException('A valid upload is required.');
        return $this->storeFile((string)$file['tmp_name'], $folder);
    }

    public function importLocalImage(string $path, string $folder='imports'): array
    {
        if (PHP_SAPI !== 'cli' || !is_file($path)) throw new \InvalidArgumentException('A valid local image is required.');
        return $this->storeFile($path, $folder);
    }

    private function storeFile(string $path, string $folder): array
    {
        $size=filesize($path);
        if ($size === false || $size > 8 * 1024 * 1024) throw new \InvalidArgumentException('Images must be smaller than 8 MB.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::ALLOWED[$mime])) throw new \InvalidArgumentException('Only JPEG, PNG, WebP, and GIF images are allowed.');
        [$width,$height] = getimagesize($path) ?: [0,0];
        if (!$width || !$height || $width > 12000 || $height > 12000) throw new \InvalidArgumentException('The uploaded image is invalid or too large.');
        $key = trim($folder,'/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(18)) . '.' . self::ALLOWED[$mime];
        $body = file_get_contents($path);
        if ($body === false) throw new \RuntimeException('Could not read upload.');
        $this->request('PUT', $key, $body, $mime);
        return ['object_key'=>$key,'url'=>rtrim((string)Env::get('R2_PUBLIC_URL'),'/').'/'.$key,'mime_type'=>$mime,'size_bytes'=>strlen($body),'width'=>$width,'height'=>$height];
    }

    public function delete(string $key): void { $this->request('DELETE', $key); }

    public function healthCheck(): void
    {
        $key = '.healthcheck/' . bin2hex(random_bytes(12)) . '.txt';
        $uploaded = false;
        try {
            $this->request('PUT', $key, 'Traditional News R2 connectivity check', 'text/plain');
            $uploaded = true;
        } finally {
            if ($uploaded) $this->delete($key);
        }
    }

    private function request(string $method, string $key, string $body='', string $contentType='application/octet-stream'): void
    {
        $bucket=(string)Env::get('R2_BUCKET_NAME'); $region=(string)Env::get('R2_REGION','auto');
        $path='/'.$bucket.'/'.implode('/',array_map('rawurlencode',explode('/',$key)));
        $host=parse_url($this->endpoint,PHP_URL_HOST); $now=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        $date=$now->format('Ymd'); $timestamp=$now->format('Ymd\THis\Z'); $payloadHash=hash('sha256',$body);
        $headers="content-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$timestamp}\n";
        $signed='content-type;host;x-amz-content-sha256;x-amz-date';
        $canonical="{$method}\n{$path}\n\n{$headers}\n{$signed}\n{$payloadHash}";
        $scope="{$date}/{$region}/s3/aws4_request";
        $toSign="AWS4-HMAC-SHA256\n{$timestamp}\n{$scope}\n".hash('sha256',$canonical);
        $h=fn(string $key,string $data): string=>hash_hmac('sha256',$data,$key,true);
        $signing=$h($h($h($h('AWS4'.Env::get('R2_SECRET_ACCESS_KEY'),$date),$region),'s3'),'aws4_request');
        $signature=hash_hmac('sha256',$toSign,$signing);
        $authorization='AWS4-HMAC-SHA256 Credential='.Env::get('R2_ACCESS_KEY_ID').'/'.$scope.', SignedHeaders='.$signed.', Signature='.$signature;
        $ch=curl_init($this->endpoint.$path);
        $options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>['Authorization: '.$authorization,'Content-Type: '.$contentType,'Host: '.$host,'x-amz-content-sha256: '.$payloadHash,'x-amz-date: '.$timestamp]];
        $caBundle=Env::get('CA_BUNDLE_PATH');
        if($caBundle)$options[CURLOPT_CAINFO]=$caBundle;
        curl_setopt_array($ch,$options);
        if ($method==='PUT') curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $response=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $error=curl_error($ch);
        if ($status<200 || $status>=300) { error_log("R2 {$method} failed: {$status} {$error} {$response}"); throw new \RuntimeException('Cloud image storage request failed.'); }
    }
}
