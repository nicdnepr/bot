<?php

namespace bot;

class Queue
{
    const STATUS_NEW = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAIL = 2;

    const PATH = 'images';

    private $_db;

    public function __construct()
    {
        try {
            $this->_db = new \MysqliDb(DB::HOST, DB::USER, DB::PASSWORD, DB::DB_NAME);
            $this->_db->connect();
        } catch (\Exception $ex) {
            echo $ex->getMessage();
            exit;
        }
    }

    public function schedule($file)
    {
        if (!file_exists($file)) {
            $file = __DIR__ . DIRECTORY_SEPARATOR . $file;
        }

        if (!file_exists($file)) {
            throw new \Exception('no file');
        }

        $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($urls as $url) {

            if (strpos($url, 'http') === 0) {
                $this->_db->insert('queue', [
                    'url' => $url
                ]);
            }

            
        }
    }

    public function download()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . self::PATH . DIRECTORY_SEPARATOR;
        if (!is_dir($path)) {
            if (!mkdir($path)) {
                throw new \Exception('can\'t create ' . self::PATH);
            }
        }

        $this->_db->where('status', self::STATUS_NEW);
        $rows = $this->_db->get('queue');

        foreach ($rows as $row) {

            if (!$this->check404($row['url'])) {
                $this->setFailRecord($row['id']);
                continue;
            }

            $pathinfo = pathinfo($row['url']);
            $name = md5(uniqid()) . '.' . $pathinfo['extension'];

            if (!copy($row['url'], $path . $name)) {
                $this->setFailRecord($row['id']);
                continue;
            }

            $this->_db->where ('id', $row['id']);
            $this->_db->update ('queue', [
                'file' => $name,
                'status' => self::STATUS_SUCCESS
            ]);
        }
    }

    public function check404($url)
    {
        $headers = @get_headers($url);
        return strpos($headers[0],'200') > 0;
    }

    private function setFailRecord($id)
    {
        $this->_db->where ('id', $id);
        $this->_db->update ('queue', [
            'status' => self::STATUS_FAIL
        ]);
    }
}
