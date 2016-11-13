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


<!--
/*
$str = <<<Eof
<script language="javascript">
var data = {$result};
function generateTree( arr )
{

}
var result = get_array( data );
alert( result );
</script>
Eof;

echo $str;
*/

//要展示的表格总数

//$wh_condition_cnt = 'parent_id = 0';
//
//$sql_cnt = 'SELECT COUNT(*) AS cnt FROM '.DB3_PREFIX.'user_tag WHERE '.$wh_condition_cnt;
//$total = $conn_3->query($sql_cnt);
//$tmp = $total->fetch_array(MYSQLI_ASSOC);
//$total = intval($tmp['cnt']);
//print_r($total);
//exit;//  14

//$fd_item_0 = '*';
//$wh_condition_0 = 'parent_id = 0';
//
//$sql_0 = 'SELECT ' .$fd_item_0. ' FROM ' .DB3_PREFIX.'user_tag WHERE ' .$wh_condition_0;
//$_list_0_ = $conn_3->query($sql_0);
//while ($rec = $_list_0_->fetch_array(MYSQLI_ASSOC)) {
//	$_list_0[] = $rec;
//}
//$total = sizeof($_list_0);//14


//$sqli = 'SELECT * FROM '.DB3_PREFIX.'user_tag WHERE parent_id = 当前ID";
//
//function findChildren($parent_id)
//{
//$parent_id 是要给的参数
//class UserTagRDHandle
//{
//	public function findChildren($parent_id)
//	{
//
//
//		$wh_condition_i = 'parent_id = '.$parent_id;
//		$a = ' SELECT * FROM ' .DB3_PREFIX.'user_tag WHERE ' .$wh_condition_i;
//		$_list_i_ = $conn_3->query();
//	}
//
//}

//}


/*
 * json 方案
 * **/



//查询所有的元素项
/*
$fd_item = '*';
$wh_condition_0 = 1;

$sql = 'SELECT ' .$fd_item. ' FROM ' .DB3_PREFIX.'user_tag WHERE ' .$wh_condition_0;
$_list_ = $conn_3->query($sql);
while ($rec = $_list_->fetch_array(MYSQLI_ASSOC)) {
	$_list[] = $rec;
}




echo '<table border="0" style="border:2px solid #666; margin:0 0 20px 0" >';
foreach($_list as $k => $v)
{
	if($v['parent_id'] == 0)
	{
		echo '<tr style="backgroud-color:#333">';
		echo '<td id='.$v['tag_id'].' style="text-align:center;word-wrap:break-word;word-break:break-all;width:100px;" onclick="show('.$v["tag_id"].')">'.$v['tag_name'].'</td>';
		echo '</tr>';
	}
}
*/
//$list = json_encode($_list);
//echo "<pre>";
//print_r($list);exit;ascript> var listi =".$list.";</script>";//






/*
<script language=javascript>

//[{"tag_id":"1","tag_name":"SAAS","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"2","tag_name":"\u534f\u4f5c\u5de5\u5177","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"3","tag_name":"CRM","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"4","tag_name":"\u4f1a\u52a1","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"5","tag_name":"\u8fdb\u9500\u5b58","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"6","tag_name":"OA","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"7","tag_name":"SCM","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"8","tag_name":"BT","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"9","tag_name":"ERP","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"10","tag_name":"HR","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"11","tag_name":"\u7efc\u5408\u529e\u516c","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"12","tag_name":"\u4f9b\u5e94\u94fe","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"13","tag_name":"\u8d22\u52a1\u7ba1\u7406","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"14","tag_name":"\u5ba2\u670d","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"15","tag_name":"\u9500\u552e","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"16","tag_name":"\u62a5\u9500\u5dee\u65c5","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"17","tag_name":"\u5916\u52e4","parent_id":"1","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,1","is_bottom":"1"},{"tag_id":"18","tag_name":"\u5e7f\u544a\u8425\u9500","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"19","tag_name":"\u6574\u4f53\u8425\u9500","parent_id":"18","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,18","is_bottom":"1"},{"tag_id":"20","tag_name":"\u5fae\u4fe1\u8425\u9500","parent_id":"18","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,18","is_bottom":"1"},{"tag_id":"21","tag_name":"\u5f71\u89c6\u8425\u9500","parent_id":"18","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,18","is_bottom":"1"},{"tag_id":"22","tag_name":"\u4eba\u529b\u8d44\u6e90","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"23","tag_name":"\u62db\u8058\u5de5\u5177","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"24","tag_name":"\u4eba\u529b\u7ba1\u7406","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"25","tag_name":"\u730e\u5934\u670d\u52a1","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"26","tag_name":"\u62db\u8058\u793e\u4ea4","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"27","tag_name":"\u884c\u4e1a\u62db\u8058","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"28","tag_name":"\u84dd\u9886\u62db\u8058","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"29","tag_name":"\u5168\u884c\u4e1a\u62db\u8058","parent_id":"22","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,22","is_bottom":"1"},{"tag_id":"30","tag_name":"\u5f00\u53d1\u670d\u52a1","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"31","tag_name":"\u6d4b\u8bd5","parent_id":"30","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,30","is_bottom":"1"},{"tag_id":"32","tag_name":"APP","parent_id":"30","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,30","is_bottom":"1"},{"tag_id":"33","tag_name":"\u901a\u8baf","parent_id":"30","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,30","is_bottom":"1"},{"tag_id":"34","tag_name":"\u670d\u52a1\u5e73\u53f0","parent_id":"30","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,30","is_bottom":"1"},{"tag_id":"35","tag_name":"API\u63a5\u53e3","parent_id":"30","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,30","is_bottom":"1"},{"tag_id":"36","tag_name":"\u4e91\u8ba1\u7b97","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"37","tag_name":"\u4e91\u5b89\u5168","parent_id":"36","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,36","is_bottom":"1"},{"tag_id":"38","tag_name":"CDN\u52a0\u901f","parent_id":"36","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,36","is_bottom":"1"},{"tag_id":"39","tag_name":"\u5927\u6570\u636e\u8ba1\u7b97","parent_id":"36","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,36","is_bottom":"1"},{"tag_id":"40","tag_name":"\u4e91\u670d\u52a1","parent_id":"36","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,36","is_bottom":"1"},{"tag_id":"41","tag_name":"\u4e91\u5b58\u50a8","parent_id":"36","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,36","is_bottom":"1"},{"tag_id":"42","tag_name":"\u5927\u6570\u636e","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"43","tag_name":"\u89c6\u9891\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"44","tag_name":"\u7535\u5546\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"45","tag_name":"\u91d1\u878d\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"46","tag_name":"\u6e38\u620f\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"47","tag_name":"\u5a31\u4e50\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"48","tag_name":"\u5927\u6570\u636e\u5206\u6790","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"49","tag_name":"\u5927\u6570\u636e\u5e73\u53f0","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"50","tag_name":"\u4f01\u4e1a\u5927\u6570\u636e","parent_id":"42","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,42","is_bottom":"1"},{"tag_id":"51","tag_name":"\u6280\u672f\u7c7b","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"52","tag_name":"\u6570\u636e\u5e93","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"53","tag_name":"\u8bed\u97f3","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"54","tag_name":"\u89c6\u89c9","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"55","tag_name":"\u7f51\u7edc","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"56","tag_name":"\u8fd0\u7ef4","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"57","tag_name":"\u652f\u4ed8","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"58","tag_name":"\u793e\u4ea4","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"59","tag_name":"\u6280\u672f\u5916\u5305","parent_id":"51","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,51","is_bottom":"1"},{"tag_id":"60","tag_name":"IT\u7ba1\u7406","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"61","tag_name":"\u4f01\u4e1a\u670d\u52a1","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"62","tag_name":"\u5b75\u5316\u670d\u52a1","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"63","tag_name":"\u54a8\u8be2","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"64","tag_name":"\u516c\u8bc1\u8ba4\u8bc1","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"65","tag_name":"\u7269\u6d41","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"66","tag_name":"\u62db\u6807","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"67","tag_name":"\u793e\u4fdd","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"68","tag_name":"\u8c03\u7814","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"69","tag_name":"\u56e2\u5efa","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"70","tag_name":"\u4f53\u68c0","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"71","tag_name":"\u793c\u54c1","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"72","tag_name":"\u529e\u516c\u793e\u4fdd","parent_id":"61","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,61","is_bottom":"1"},{"tag_id":"73","tag_name":"\u4fe1\u606f\u5b89\u5168","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"74","tag_name":"\u7f51\u7edc\u5b89\u5168","parent_id":"73","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,73","is_bottom":"1"},{"tag_id":"75","tag_name":"\u4f01\u4e1a\u5b89\u5168","parent_id":"73","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,73","is_bottom":"1"},{"tag_id":"76","tag_name":"\u79fb\u52a8\u5b89\u5168","parent_id":"73","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,73","is_bottom":"1"},{"tag_id":"77","tag_name":"\u91d1\u878d\u5b89\u5168","parent_id":"73","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,73","is_bottom":"1"},{"tag_id":"78","tag_name":"\u5546\u52a1\u5b89\u5168","parent_id":"73","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,73","is_bottom":"1"},{"tag_id":"79","tag_name":"\u4fe1\u606f\u5b89\u5168","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"80","tag_name":"\u7f51\u7edc\u5b89\u5168","parent_id":"79","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0,79","is_bottom":"1"},{"tag_id":"81","tag_name":"\u8425\u9500\u5de5\u5177","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"82","tag_name":"\u6cd5\u5f8b\u670d\u52a1","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"},{"tag_id":"83","tag_name":"\u7269\u8054\u7f51","parent_id":"0","create_time":"1610081711","update_time":"1610081711","is_del":"0","create_user_id":"0","related_ids":"0","is_bottom":"0"}]

var str = '[{"uname":"王强","day":"2010/06/17"},{"uname":"王海云","day":"2010/06/11"}]';
var jsonList=eval("("+str+")");
for(var i=0;i<jsonList.length;i++){

    for(var key in jsonList[i]){
        alert("key："+key+",value："+jsonList[i][key]);
    }
}
</script>
*/