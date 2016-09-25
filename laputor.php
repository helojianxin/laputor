<?php
/***
 *
 *  从目标豆瓣某电影页提取元素推荐三个相关电影,并提供相关电影信息
 *  推荐依据:同导演一部、同编剧一部、同主演一部
 *  相关电影信息包括:简介、有用数最高的影评、海报、中英文电影名、影片豆瓣链接、豆瓣评分数、影评总数
 *
 *  输出的关联数组,按照:豆瓣评分>影评数 的优先级排序
 *
 *  author:jessey
 *  email:helojianxin@163.com
 *
 ****/


/*
 * 目标页
 * **/

require_once 'QueryList/vendor/autoload.php';
use QL\QueryList;

//目标豆瓣电影页
//$html = "https://movie.douban.com/subject/22939161/";

$html = $_POST['ori'];
//echo $html;exit;
//采集所有的导演、编剧、主角
//暂时默认取第一个
$data = QueryList::Query($html,array(
        'dir' => array('div#info>span:eq(0)>span.attrs>a:eq(0)','text'),
        'scr' => array('div#info>span:eq(1)>span.attrs>a:eq(0)','text'),
        'act' => array('div#info>span.actor>span.attrs>a:eq(0)','text')
    )
)->data;

$names = array();
$names = $data[0];

/*
 * 查询结果页
 * **/

//拼接查询的url
$url_0="https://movie.douban.com/subject_search?search_text={$names['dir']}&cat=1002";
$url_1="https://movie.douban.com/subject_search?search_text={$names['scr']}&cat=1002";
$url_2="https://movie.douban.com/subject_search?search_text={$names['act']}&cat=1002";

$film_data = array();

for($i=0;$i<3;$i++)
{
    $str = 'url_'.$i;
    $url = $$str;

    $data = QueryList::Query($url,array(
            //film_names 包含中英文电影名 可多个 间隔符号 /
            'film_poster'   => array('div#content>div:eq(0)>div.article>div:eq(1)>table>tr>td:eq(0)>a>img','src'),
            'film_names'    => array('div#content>div:eq(0)>div.article>div:eq(1)>table>tr>td:eq(1)>div.pl2>a:eq(0)','text'),
            'film_url'      => array('div#content>div:eq(0)>div.article>div:eq(1)>table>tr>td:eq(1)>div.pl2>a:eq(0)','href'),
            'rating_num'    => array('div#content>div:eq(0)>div.article>div:eq(1)>table>tr>td:eq(1)>div.pl2>div.star>span:eq(1)','text'),
            'comments_num'  => array('div#content>div:eq(0)>div.article>div:eq(1)>table>tr>td:eq(1)>div.pl2>div.star>span:eq(2)','text')
        )
    )->data;

    switch($i)
    {
        case 0:
            $base_key = 'dir';
            $prefix = '[同导演]';
            break;
        case 1:
            $base_key = 'scr';
            $prefix = '[同编剧]';
            break;
        case 2:
            $base_key = 'act';
            $prefix = '[同主演]';
            break;
        default:
            break;
    }
    $data[0]['base_name'] = $prefix.$names[$base_key];
    $film_data[] = $data[0];

    sleep(0.001*(50+rand(0,9)));//延迟50ms,不然有时候结果返回不全 随机数是为了模拟自然请求
}
$film_data = array_combine(array('dir','scr','act'),$film_data);


/*
 * 相关影片页面
 * **/

$related_data = array();
foreach($film_data as $k => $v)
{
    $url = $v['film_url'];
    $data = QueryList::Query($url,array(
            //剧情简介、最有用评论
            'synopsis'      => array('div#link-report>span','text'),
            'recommended'   => array('div#hot-comments>div:eq(0)>div>p','text')
        )
    )->data;

    $related_data[] = $data[0];
}
$related_data = array_combine(array('dir','scr','act'),$related_data);

//把两数组同键名元素合并

//echo "<pre>";
//print_r($related_data);exit;


$result = array();
foreach($related_data as $k => $v)
{
    $result[] = array_merge($v,$film_data[$k]);
}

$result = array_combine(array('dir','scr','act'),$result);

//关键字检查  (展开全部) 本开发环境一个汉字三个字符
foreach($result as $k => &$v)
{
    foreach($v as $k1 => &$v1)
    {
        if(strpos($v1,'(展开全部)') !== false)
        {
            $v1 = substr($v1,0,strlen($v1)-14);
        }
        //$v1 = iconv("UTF-8","GB2312//IGNORE",$v1);//转换页面编码
    }

}
unset($v1,$v);//取消之前用的引用

$arr1 = array('dir' => $result['dir']['rating_num'],'scr' => $result['scr']['rating_num'],'act' =>  $result['act']['rating_num']);
$arr2 = array('dir' => $result['dir']['comments_num'],'scr' => $result['scr']['comments_num'],'act' => $result['act']['comments_num']);

array_multisort($arr1,SORT_DESC,SORT_NUMERIC,$arr2,SORT_DESC);

$arr = array_keys($arr2);

$final = array();

foreach($arr as $k => $v)
{
//    $final[$v] = $result[$v];
    $final[] = $result[$v];
}

if($final)
{
    if(strpos($_SERVER["HTTP_USER_AGENT"],'Chrome') && strpos($_SERVER["HTTP_USER_AGENT"],'Mac OS X'))
    {
        echo "可能是因为chrome强制字体设置的原因,本浏览器暂时无法正常显示(推荐用Safari浏览),下面直接打印生成的数据:<pre>";
        print_r($final);
        //include_once "result_chrome.html";
    }
    else{
        include_once "result.html";
    }

}else{
    if(strpos($_SERVER["HTTP_USER_AGENT"],'Chrome') && strpos($_SERVER["HTTP_USER_AGENT"],'Mac OS X'))
    {
        include_once "index_chrome.html";
    }
    else{
        include_once "index.html";
    }

}
