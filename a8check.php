<?php 
if( $argc != 2 ){
    echo '第一引数にブログのドメインを指定してください';
    exit;
}

$domain = $argv[1];
$robotsTxtUrl = 'https://' . $domain . '/robots.txt';
$robotsTxt = file_get_contents($robotsTxtUrl);
echo ' >> ' . $robotsTxtUrl . PHP_EOL;

if(preg_match('@sitemap:\s*([^\s]*)@i', $robotsTxt, $matched)){
    $sitemapIndexUrl = $matched[1];
}else{
    echo 'サイトマップが見つかりません' . PHP_EOL;
    exit;
}
echo 'sitemap index xml ='.$sitemapIndexUrl . PHP_EOL;


$sitemapOrIndexUrls = [];
$sitemapOrIndexUrls[] = $sitemapIndexUrl;

do{
    $url = array_shift($sitemapOrIndexUrls);
    echo ' >> ' . $url . PHP_EOL;

    $response = file_get_contents($url);
    $urlSet = new SimpleXMLElement($response);
    echo '    ' . count($urlSet) . ' entries.' . PHP_EOL;

    foreach($urlSet as $name=>$data){
        $loc =  (String)$data->loc;
        if($name == 'sitemap'){
            $sitemapOrIndexUrls[] = $loc;
            echo ' sitemap URL :' . $loc . PHP_EOL;
        }elseif($name == 'url'){
            $sitemap = [];
            $sitemap['loc'] = (string)$data->loc;
            $sitemap['lastmod'] = toJST((string)$data->lastmod);
            $list[] = $sitemap;
            // echo ' page :' . $sitemap['lastmod'] . ' ' . $sitemap['loc'] .PHP_EOL;
        }else{
            echo " somethign wrong <$name> tag." . PHP_EOL;
        }
    }
}while(!empty($sitemapOrIndexUrls));

//更新日の新しい順に並び替え（変わってないものはリクエスト不要）
foreach($list as $n => $sitemap){
    $sort_keys[$n] = $sitemap['lastmod'];
}
array_multisort($sort_keys, SORT_DESC, $list);

echo '=== URL ====' . PHP_EOL;
echo count($list)   . PHP_EOL;
echo '============' . PHP_EOL;

$context = stream_context_create(array('http' => array('follow_location' => true)));

$result = [];
$midCache = [];
$clicks = 0;

foreach( $list as $s=>$sitemap){

    echo '* ' . ($s+1) . '/' . count($list) . ' | ' . $sitemap['loc'] . PHP_EOL;

    $html = file_get_contents($sitemap['loc']);
    preg_match_all('@<a href="(https://px.a8.net/[^"]*)"[^>]*>(.*?)</a>@is',$html, $_a8matchResult, PREG_SET_ORDER );

    echo '  Html size:' . strlen($html) . ' ' . count($_a8matchResult)  .' matched.'. PHP_EOL;

    foreach($_a8matchResult as $m=>$matched){
        echo ' --' . ($m+1) . '--' . PHP_EOL;
        // var_dump($matched);
        $a8Url = $matched[1];
        $linkText = "";
        $type = "";

        //<a>タグ内部からsで始まるmidがあったら画像形式で確定
        $mid = getMidInParam($matched[2]);
        if($mid){
            $type="画像";
        }else{
            $type="テキスト";
            $linkText = $matched[2];

            //プログラム実行中に見つけたURLとMIDの組み合わせがあれば、それを採用。何もなければリンク先に行ってみてURLの一部からsで始まる記号を探す
            $mid = getMidCache($midCache, $a8Url);
            if($mid){
                echo '   cache:' . $a8Url . ' -> ' . $mid . PHP_EOL;
            }else{
                echo "  >> " . $a8Url . PHP_EOL;
                sleep(5);
                $clicks++;
                $a8clicked = file_get_contents($a8Url, false, $context);
                $nextUrl = getLocation($http_response_header);
                $mid = getMidInUrl($nextUrl);
            }
        }
        saveMidCache($midCache, $a8Url, $mid);

        echo "  A8:" . $a8Url . PHP_EOL;
        echo "  ID:" . $mid . PHP_EOL;
        echo "  Text:" . $linkText . PHP_EOL;
        $result[] = ['url'=>$sitemap['loc'],'mid'=>$mid,'linktext'=>$linkText,'a8'=>$a8Url,'type'=>$type,];
    }
    usleep(1000*1000);
}

echo '=== Result ====' . PHP_EOL;
echo 'a8 :' . count($result)   . PHP_EOL;
echo 'click :' . $clicks   . PHP_EOL;
echo '===============' . PHP_EOL;

$csvFile = $domain .'_'. date('Ymd-His') . '.csv';

foreach($result as $n=>$data){
    $line = $data['mid'] .','. $data['url'] .','. $data['linktext'] .','. $data['a8'] .','. $data['type'] . PHP_EOL;
    file_put_contents($csvFile, $line, FILE_APPEND);
}

echo $csvFile . ' を作成しました' . PHP_EOL;
echo '-- end --'. PHP_EOL;

//Google APIのタイムスタンプがnano秒まであるので正規表現で削り取る
function toJST($datetime){
    $p = '/(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})\.[0-9]{9}Z/';
    if( preg_match($p, $datetime, $_)){
        $datetime = "$_[1]-$_[2]-$_[3]T$_[4]:$_[5]:$_[6]Z";
    }
    $t = new DateTime($datetime);
    $t->setTimeZone(new DateTimeZone('Asia/Tokyo'));
    return $t->format('Y-m-d H:i:s');
}

function getLocation($headers){
    foreach( $headers as $n=>$header){
        if( preg_match( '@Location: (https:.*)@', $header, $_) ){
            return $_[1];
        }
    }
    return null;
}
function getMidInParam($content){
    if(preg_match('@mid=(s[0-9]{14})@', $content, $_ ) ){
        return $_[1];
    }
    return null;
}
function getMidInUrl($content){
    if(preg_match('@s[0-9]{14}@', $content, $_ ) ){
        return $_[0];
    }
    return null;
}

function getMidCache(&$arr, $url){
    $key = implode('+', explode('+', $url, -1));
    return $arr[$key]??null;
}

function saveMidCache(&$arr, $url, $mid){
    if( !$mid ){ return; }
    $key = implode('+', explode('+', $url, -1));
    $arr[$key]=$mid;
}