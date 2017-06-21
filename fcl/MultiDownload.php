<?php
/**
 * Created by IntelliJ IDEA.
 * User: fang.cai.liang@aliyun.com
 * Date: 2017/6/21
 * Time: 09:57
 */

namespace fcl;


class MultiDownload
{

    private $config = [

        'thread_num' => 4,

        //参数的意义见: http://www.voidcn.com/blog/sole_cc/article/p-6146704.html
        'curl_opts' => [
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 1800,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]
    ];
    
    private $cmi;

    /**
     * MultiDownload constructor.
     * @param array $config
     */
    function __construct($config = [])
    {
        if(!empty($config)){
            $this->config = array_merge($this->config, $config);
        }
        $this->cmi = curl_multi_init();
    }

    /**
     * 根据下载链接生成 文件的名字
     * @param $fileUrl
     * @return string
     */
    private function genFileName($fileUrl){
        $urlInfo = parse_url($fileUrl);
        if (empty($urlInfo)) {
            return '';
        }
        $urlPaths = pathinfo($urlInfo['path']);
        if (empty($urlPaths)) {
            return '';
        }
        $basename = urldecode($urlPaths['basename']);
        return time().'___'.$basename;
    }

    /**
     * @param $urls
     * @param $destDir
     */
    private function initTask(&$urls, $destDir){
        $fileUrl = array_shift($urls);
        $task = curl_init($fileUrl);
        $destfile = fopen($destDir.($this->genFileName($fileUrl)), 'w');
        $this->config['curl_opts'][CURLOPT_FILE] = $destfile;
        curl_setopt_array($task, $this->config['curl_opts']);
        curl_multi_add_handle($this->cmi, $task);
    }

    /**
     * @param array $urls 需要下载的文件列表
     * @param $destDir 本地存放的目录
     * @param $downloadLog 记录日志的文件路径
     */
    public function download(Array $urls, $destDir, $downloadLog)
    {
        $threadNum = min($this->config['thread_num'], count($urls));
        for ($i = 0; $i < $threadNum; $i++) {
            $this->initTask($urls, $destDir);
        }
        $total_time = 0;
        do {
            do {
                curl_multi_exec($this->cmi, $runningNum);
                if(curl_multi_select($this->cmi, 1.0) > 0)
                    break;
            } while ($runningNum);

            while ($info = curl_multi_info_read($this->cmi)) {
                $ch = $info['handle'];
                $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $total_time += curl_getinfo($ch, CURLINFO_TOTAL_TIME);

                if($info['result'] == CURLE_OK) {
                    $log = date('Y-m-d H:i:s').'__INFO::成功下载 '.$url.' 文件'.PHP_EOL;
                    file_put_contents($downloadLog, $log, FILE_APPEND);
                    echo $log;
                } else{
                    echo date('Y-m-d H:i:s').'__ERROR::下载文件 : '.$url.' 出错!'.curl_error($ch).PHP_EOL;
                }
                curl_multi_remove_handle($this->cmi, $ch);
                curl_close($ch);
                unset($ch);

                if (!empty($urls)) {
                    $this->initTask($urls, $destDir);
                    curl_multi_exec($this->cmi, $runningNum);
                }
            }
        } while ($runningNum);
        curl_multi_close($this->cmi);
    }
}