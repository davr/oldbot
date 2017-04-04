<?php
require __DIR__ . '/vendor/autoload.php';

use Aws\SimpleDb\SimpleDbClient;

require_once("/home/davidr/slack/config.php");

$sdb = new SimpleDbClient([
    'version' => 'latest',
    'region' => 'us-east-1',
    'credentials' => [
        'key'=>$AWS_KEY,
        'secret'=>$AWS_SECRET
        ]
    ]);

#if($_POST['token'] != $SLACK_TOKEN) die('no');

$pattern = "/<(http[^@|>]*)\|?([^>]*)?>/";
$text = $_POST['text'];
$user = $_POST['user_name'];
$time = time();
$matches = [];
$n = preg_match_all($pattern, $text, $matches);

function time_since($since) {
    $chunks = array(
        array(60 * 60 * 24 * 365 , 'year'),
        array(60 * 60 * 24 * 30 , 'month'),
        array(60 * 60 * 24 * 7, 'week'),
        array(60 * 60 * 24 , 'day'),
        array(60 * 60 , 'hour'),
        array(60 , 'minute'),
        array(1 , 'second')
    );

    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];
        if (($count = floor($since / $seconds)) != 0) {
            break;
        }
    }

    $print = ($count == 1) ? '1 '.$name : "$count {$name}s";
    return $print;
}


function result($obj) {
    $user = $obj['user'];
    $time = $obj['time'];
    $time = time_since(time() - $time);
    $msg = ":warning: OLD URL $user posted $time ago OLD URL :warning:";
    print(json_encode(['text'=>$msg, 'username'=>'OldMemeBot']));
}

file_put_contents("slack.log","$time|$user|$text\n", FILE_APPEND);
foreach($matches[1] as $n=>$url) {
    if(strlen($matches[2][$n])>2) $label = $matches[2][$n]; else $label = $url;
    file_put_contents("slack.log","  $url\n", FILE_APPEND);
    $url = strtolower($url);
    if(strlen($url) < 5 || $url == "") continue;

    $res = $sdb->getAttributes(['DomainName'=>'oldbot','ConsistentRead'=>true,
        'ItemName'=>$url]);

    if(isset($res['Attributes'])) {
        file_put_contents("slack.log","     OLD\n", FILE_APPEND);
        $obj = [];
        foreach($res['Attributes'] as $attr) {
            if($attr['Name']=='user') $obj['user'] = $attr['Value'];
            if($attr['Name']=='ts') $obj['time'] = $attr['Value'];
        }

        if($obj['user'] == $user)
            continue;

        result($obj);
        die();
    }
    else {
        $sdb->putAttributes(['DomainName'=>'oldbot','ItemName'=>$url,'Attributes'=>[
            ['Name'=>'user','Value'=>$user,'Replace'=>true],
            ['Name'=>'ts','Value'=>$time,'Replace'=>true]]
        ]);
    }
}


