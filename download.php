<?php
/**
 * Created by IntelliJ IDEA.
 * User: fang.cai.liang@aliyun.com
 * Date: 2017/6/18
 * Time: 20:30
 */

date_default_timezone_set('PRC');

/**
 * 获取网页的内容
 * @param $url
 * @return string
 */
function remote_content($url)
{
    $handle = fopen($url, 'rb');
    $contents = '';
    if($handle){
        while('' != ($data = fread($handle, 8192))){
            $contents .= $data;
        }
        fclose($handle);
    }
    return $contents;
}

/**
 * 通过解析网页内容, 获取文件列表
 * @param $url
 * @return array
 */
function remote_files($url)
{
    echo date('Y-m-d H:i:s').'__开始获取 '.$url.' 下的文件列表...'.PHP_EOL;
    $contents = remote_content($url);
    $files = [];
    if (!empty($contents)) {
        preg_match_all('/href="([^"]*[^\/$])"/', $contents, $files);
    }
    return $files;
}

/**
 * 通过解析网页内容, 获取目录列表
 * @param $url
 * @return array
 */
function remote_dirs($url)
{
    echo date('Y-m-d H:i:s').'__开始获取 '.$url.' 下的目录列表...'.PHP_EOL;
    $contents = remote_content($url);
    $dirs = [];
    if (!empty($contents)) {
        preg_match_all('/href="([^"]*[\/$])"/', $contents, $dirs);
    }
    return $dirs;
}

/**
 * 解析 remote_dirs 的返回值, 过滤掉 当前目录（/） 和 Parent Directory
 * @param $dirs
 * @return array
 */
function parse_dir($dirs){
    $dirList = [];
    if(!empty($dirs)){
        foreach ($dirs[1] as $dir) {
            if(('/' != $dir) && (0 !== strpos($dir, '/'))){
                $dirList[] = $dir;
            }
        }
    }
    return $dirList;
}

/**
 * 通过解析网页内容, 递归调用, 获取目录结构树, key 就是目录完整路径
 * @param $url
 * @param $dirList
 */
function dir_tree($url, &$dirList){
    $dirs = parse_dir(remote_dirs($url));
    $dirList[$url] = $dirs;
    if(!empty($dirs)){
        foreach ($dirs as $dir) {
            dir_tree($url.$dir, $dirList);
        }
    }
}

/**
 * 通过解析网页内容,获取所有的目录,遍历目录获取所有文件
 * @param $url
 * @return array
 */
function getFileUrls($url){
    echo date('Y-m-d H:i:s').'__开始获取目录树结构...'.PHP_EOL;
    dir_tree($url, $dirList);
    $fileUrlList = [];
    if(!empty($dirList)){
        $allDirs = array_keys($dirList);
        echo date('Y-m-d H:i:s').'__开始遍历目录树获取文件列表...'.PHP_EOL;
        foreach ($allDirs as $fileDir) {
            $fileUrlList[$fileDir] = remote_files($fileDir)[1];
        }
    }
    return $fileUrlList;
}

/**
 * 获取文件的后缀
 * @param $url
 * @return string
 */
function getUrlSuffix($url)
{
    $urlInfo = parse_url($url);
    if (empty($urlInfo)) {
        return '';
    }
    $urlPaths = pathinfo($urlInfo['path']);
    if (empty($urlPaths)) {
        return '';
    }
    return $urlPaths['extension'];
}

/**
 * 根据后缀判断,该文件是否需要下载
 * @param $fileUrl
 * @param array $suffixs
 * @return bool
 */
function is_valid_url($fileUrl, $suffixs = []){
    if(empty($suffixs)){
        return true;
    }
    $fileSuffix = getUrlSuffix($fileUrl);
    if(in_array(strtolower($fileSuffix), $suffixs)){
        return true;
    }
    return false;
}

/**
 * 根据下载链接生成 文件的名字
 * @param $fileUrl
 * @return string
 */
function genFileName($fileUrl){
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
 * 下载文件, 下载成功的 记录到 $downloadLog 中
 * @param $fileUrl
 * @param $destDir
 * @param $downloadLog
 */
function download_file($fileUrl, $destDir, $downloadLog)
{
    echo date('Y-m-d H:i:s').'__开始下载文件: '.$fileUrl.PHP_EOL;
    $destfile = fopen($destDir.genFileName($fileUrl), 'w');
    $ch = curl_init($fileUrl);
    curl_setopt($ch, CURLOPT_FILE, $destfile);
    $result = curl_exec($ch);
    curl_close($ch);
    if($result){
        $log = date('Y-m-d H:i:s').'__INFO::成功下载 '.$fileUrl.' 文件,存储到 '.$destfile.PHP_EOL;
        file_put_contents($downloadLog, $log, FILE_APPEND);
        echo $log;
    }else{
        echo date('Y-m-d H:i:s').'__ERROR::下载文件 : '.$fileUrl.' 出错!'.PHP_EOL;
    }
}

/**
 * 解析 log 文件, 获取已经下载的文件列表
 * @param $downloadLog
 * @return mixed
 */
function getDownloadedUrls($downloadLog){
    echo date('Y-m-d H:i:s').'__开始解析下载日志文件: '.$downloadLog.PHP_EOL;
    $content = file_get_contents($downloadLog);
    preg_match_all('/http?:\/\/[\w-.%#?\/\\\]+/i', $content, $files);
    return $files[0];
}

/**
 * 下载服务器上的特定后缀的文件
 * @param $url 服务器地址
 * @param array $suffixs 需要下载的文件后缀数组, 不区分大小写
 * @param $destDir 本地存放的命令
 * @param $downloadLog 记录下载 log 的文件
 */
function download($url, $suffixs = [], $destDir, $downloadLog){
    echo date('Y-m-d H:i:s').'__开始解析 url: '.$url.PHP_EOL;
    $fileUrlList = getFileUrls($url);
    $finishedUrls = getDownloadedUrls($downloadLog);
    foreach ($fileUrlList as $baseUrl => $fileUrlArray) {
        if(!empty($fileUrlArray)){
            foreach ($fileUrlArray as $fileName) {
                if(is_valid_url($baseUrl.$fileName, $suffixs) && !in_array($baseUrl.$fileName, $finishedUrls)){
                    //echo $baseUrl.$fileName.PHP_EOL;
                    download_file($baseUrl.$fileName, $destDir, $downloadLog);
                }
            }
        }
    }
    echo date('Y-m-d H:i:s').'__下载完成。'.PHP_EOL;
}

download('http://192.168.1.6/', ['txt'], '/Users/fangcailiang/Downloads/win/', '/Users/fangcailiang/Downloads/win/download_success.log');
