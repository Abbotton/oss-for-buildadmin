<?php

namespace app\admin\controller\routine;

use app\common\controller\Backend;
use app\common\model\Attachment;

class Oss extends Backend
{
    protected $topic = 'default';

    public function cosRefreshToken()
    {
        $cos = \modules\oss\Oss::getSts();
        if (isset($cos['error'])) {
            $this->error($cos['message'], $cos);
        } else {
            $this->success('', $cos);
        }
    }

    public function callback()
    {
        $data = $this->request->post();
        $params = [
            'topic' => $this->topic,
            'admin_id' => $this->auth->id,
            'user_id' => 0,
            'url' => $data['url'],
            'width' => $data['width'] ?? 0,
            'height' => $data['height'] ?? 0,
            'name' => substr(htmlspecialchars(strip_tags($data['name'])), 0, 100),
            'size' => $data['size'],
            'mimetype' => $data['type'],
            'storage' => $data['storage'],
            'sha1' => $data['sha1']
        ];

        Attachment::create(array_filter($params))
            ? $this->success()
            : $this->error();
    }

    public function getConfig()
    {
        $config = config('oss', []);

        $this->success('', $config);
    }

    public function saveConfig()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            // 手动处理一下，防止恶意代码保存到配置文件。
            $config = [
                'storage_driver' => $params['storage_driver'],
                'aliyun' => [
                    'bucket' => $params['aliyun']['bucket'],
                    'access_key_id' => $params['aliyun']['access_key_id'],
                    'access_key_secret' => $params['aliyun']['access_key_secret'],
                    'url' => $params['aliyun']['url'],
                    'cdn_url' => $params['aliyun']['cdn_url'],
                ],
                'cos' => [
                    'bucket' => $params['cos']['bucket'],
                    'secret_id' => $params['cos']['secret_id'],
                    'secret_key' => $params['cos']['secret_key'],
                    'url' => $params['cos']['url'],
                    'cdn_url' => $params['cos']['cdn_url'],
                ],
                'qiniu' => [
                    'bucket' => $params['qiniu']['bucket'],
                    'access_key' => $params['qiniu']['access_key'],
                    'secret_key' => $params['qiniu']['secret_key'],
                    'url' => $params['qiniu']['url'],
                    'cdn_url' => $params['qiniu']['cdn_url'],
                ]
            ];

            $configString = '<?php'.PHP_EOL.PHP_EOL.'return '.$this->varExportFormat($config, true).';';
            file_put_contents(app()->getConfigPath().'oss.php', $configString);

            $this->success('操作成功');
        }
    }

    private function varExportFormat($expression, $return = false)
    {
        if (!is_array($expression)) {
            return var_export($expression, $return);
        }
        $export = var_export($expression, true);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = join(PHP_EOL, array_filter(["["] + $array));
        if ($return) {
            return $export;
        }

        echo $export;
    }
}
