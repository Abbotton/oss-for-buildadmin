<?php

namespace modules\oss;

use app\common\model\Attachment;
use Exception;
use OSS\OssClient;
use Qcloud\Cos\Client;
use QCloud\COSSTS\Sts;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Event;

class Oss
{
    private static $config;
    
    private $uid = 'oss_all_in_one';

    public function __construct()
    {
        self::$config = config('oss', []);
    }

    /**
     * 使用 secretId & secretKey 颁发临时凭证.
     *
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function getSts()
    {
        $sts = new Sts();
        $config = [
            'secretId' => self::$config['cos']['secret_id'],
            'secretKey' => self::$config['cos']['secret_key'],
            'bucket' => self::$config['cos']['bucket'],
            'region' => self::$config['cos']['url'],
            'durationSeconds' => 3600,
            'allowPrefix' => '*',
            'allowActions' => [
                'name/cos:PutObject',
                'name/cos:PostObject',
                'name/cos:InitiateMultipartUpload',
                'name/cos:ListMultipartUploads',
                'name/cos:ListParts',
                'name/cos:UploadPart',
                'name/cos:CompleteMultipartUpload'
            ]
        ];
        try {
            $data = $sts->getTempKeys($config);

            return [
                'tmpSecretId' => $data['credentials']['tmpSecretId'],
                'tmpSecretKey' => $data['credentials']['tmpSecretKey'],
                'sessionToken' => $data['credentials']['sessionToken'],
                'startTime' => time(),
                'expiredTime' => $data['expiredTime']
            ];
        } catch (Exception $e) {
            $error = json_decode($e->getMessage(), true);

            return [
                'error' => 1,
                'code' => $error['Error']['Code'],
                'message' => $error['Error']['Message']
            ];
        }
    }

    public function AppInit()
    {
        // 上传配置初始化
        Event::listen('uploadConfigInit', function (App $app) {
            $driver = self::$config['storage_driver'];
            if ($driver != 'local') {
                switch ($driver) {
                    case 'aliyun':
                        $bucketUrl = 'https://'.self::$config['aliyun']['bucket'].'.'.self::$config['aliyun']['url'].'.aliyuncs.com';
                        $upload = \think\facade\Config::get('upload');
                        $maxSize = file_unit_to_byte($upload['maxsize']);
                        $conditions[] = ['content-length-range', 0, $maxSize];
                        $expire = time() + 3600;
                        $policy = base64_encode(json_encode([
                            'expiration' => date('Y-m-d\TH:i:s.Z\Z', $expire),
                            'conditions' => $conditions,
                        ]));
                        $signature = base64_encode(hash_hmac('sha1', $policy, self::$config['aliyun']['access_key_secret'], true));

                        $app->request->upload = [
                            'cdn' => self::$config['aliyun']['cdn_url'] ?: $bucketUrl,
                            'mode' => 'aliyun',
                            'url' => $bucketUrl,
                            'params' => [
                                'OSSAccessKeyId' => self::$config['aliyun']['access_key_id'],
                                'policy' => $policy,
                                'Signature' => $signature,
                                'Expires' => $expire,
                            ]
                        ];
                        break;
                    case 'qiniu':
                        $auth = new Auth(self::$config['qiniu']['access_key'], self::$config['qiniu']['secret_key']);
                        $upToken = $auth->uploadToken(self::$config['qiniu']['bucket']);
                        $app->request->upload = [
                            'cdn' => self::$config['qiniu']['cdn_url'],
                            'mode' => 'qiniu',
                            'url' => self::$config['qiniu']['url'],
                            'params' => [
                                'token' => $upToken,
                            ]
                        ];
                        break;
                    case 'cos':
                        $cdn = self::$config['cos']['cdn_url'] == ''
                            ? 'https://'.self::$config['cos']['bucket'].'.cos.'.self::$config['cos']['url'].'.myqcloud.com'
                            : self::$config['cos']['cdn_url'];
                        $app->request->upload = [
                            'cdn' => $cdn,
                            'mode' => 'cos',
                            'params' => [
                                'bucket' => self::$config['cos']['bucket'],
                                'region' => self::$config['cos']['url']
                            ]
                        ];
                        break;
                }
            }
        });

        // 附件管理中删除了文件
        Event::listen('AttachmentDel', function (Attachment $attachment) {
            switch ($attachment->storage) {
                case 'aliyun':
                    if (
                        !self::$config['aliyun']['access_key_id']
                        || !self::$config['aliyun']['access_key_secret']
                        || !self::$config['aliyun']['bucket']
                    ) {
                        return true;
                    }
                    $OssClient = new OssClient(
                        self::$config['aliyun']['access_key_id'],
                        self::$config['aliyun']['access_key_secret'],
                        'https://'.self::$config['aliyun']['url'].'.aliyuncs.com'
                    );
                    $url = str_replace(full_url(), '', ltrim($attachment->url, '/'));
                    $OssClient->deleteObject(self::$config['aliyun']['bucket'], $url);
                    break;
                case 'qiniu':
                    if (
                        !self::$config['qiniu']['access_key']
                        || !self::$config['qiniu']['secret_key']
                        || !self::$config['qiniu']['bucket']
                    ) {
                        return true;
                    }
                    $auth = new Auth(self::$config['qiniu']['access_key'], self::$config['qiniu']['secret_key']);
                    $config = new \Qiniu\Config();
                    $bucketManager = new BucketManager($auth, $config);
                    $url = str_replace(full_url(), '', ltrim($attachment->url, '/'));
                    $bucketManager->delete(self::$config['qiniu']['bucket'], $url);
                    break;
                case 'cos':
                    if (
                        !self::$config['cos']['secret_id']
                        || !self::$config['cos']['secret_key']
                        || !self::$config['cos']['bucket']
                    ) {
                        return true;
                    }
                    $config = [
                        'region' => self::$config['cos']['url'],
                        'credentials' => [
                            'secretId' => self::$config['cos']['secret_id'],
                            'secretKey' => self::$config['cos']['secret_key']
                        ]
                    ];
                    $cosClient = new Client($config);
                    $key = str_replace(full_url(), '', ltrim($attachment->url, '/'));
                    $cosClient->deleteObject([
                        'Bucket' => self::$config['cos']['bucket'],
                        'Key' => $key
                    ]);
                    break;
            }

            return true;
        });
    }
}
