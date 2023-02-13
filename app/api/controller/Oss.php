<?php

namespace app\api\controller;

use app\common\controller\Backend;
use app\common\model\Attachment;

class Oss extends Backend
{
    protected $topic = 'default';

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
}
