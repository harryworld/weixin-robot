<?php

/*
Plugin Name: 微信机器人
Plugin URI: http://blog.wpjam.com/project/weixin-robot/
Description: 微信机器人的主要功能就是能够将你的公众账号和你的 WordPress 博客联系起来，搜索和用户发送信息匹配的日志，并自动回复用户，让你使用微信进行营销事半功倍。
Version: 0.1.1
Author: Denis
*/

//define your token
define("TOKEN", "weixin");

add_action('init', 'wpjam_wechat_redirect', 4);
function wpjam_wechat_redirect(){
    if(isset($_GET['weixin'])){
        $wechatObj = new wechatCallback();
        $wechatObj->valid();
        exit;
    }
}

class wechatCallback
{
    private $items = '';
    private $articleCount = 0;
    private $keyword = '';

    public function valid()
    {
        if(isset($_GET['debug'])){

            $this->keyword = $_GET['s'];
            $this->search();

            echo $this->items;
        }

        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            $this->responseMsg();
            
            exit;
        } else {
            // FIXME: Display text if signature is wrong, good for debug
            // if (isset($_GET['debug'])) {
                echo 'Error Signature';
            // }
        }
    }

    public function responseMsg()
    {
        //get post data, May be due to the different environments
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

        //extract post data
        if (!empty($postStr)){
                
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $this->keyword = strtolower(trim($postObj->Content));

                $time = time();
                $textTpl = "<xml>
                            <ToUserName><![CDATA[".$fromUsername."]]></ToUserName>
                            <FromUserName><![CDATA[".$toUsername."]]></FromUserName>
                            <CreateTime>".$time."</CreateTime>
                            <MsgType><![CDATA[text]]></MsgType>
                            <Content><![CDATA[%s]]></Content>
                            <FuncFlag>0</FuncFlag>
                            </xml>";     
                $picTpl = "<xml>
                             <ToUserName><![CDATA[".$fromUsername."]]></ToUserName>
                             <FromUserName><![CDATA[".$toUsername."]]></FromUserName>
                             <CreateTime>".$time."</CreateTime>
                             <MsgType><![CDATA[news]]></MsgType>
                             <Content><![CDATA[]]></Content>
                             <ArticleCount>%d</ArticleCount>
                             <Articles>
                             %s
                             </Articles>
                             <FuncFlag>1</FuncFlag>
                            </xml>";
                if($this->keyword == 'hi' || $this->keyword == '您好'  || $this->keyword == '你好' ||$this->keyword == 'hello2bizuser' ){
                    $contentStr = "输入关键字开始搜索！";//自定义欢迎回复;
                    echo sprintf($textTpl, $contentStr);
                }else if( !empty( $this->keyword )){
                    $this->search();
                    if($this->articleCount == 0){
                        $contentStr = "抱歉，没有找到与【{$this->keyword}】相关的文章，要不你更换一下关键字，可能就有结果了哦 :-) ";
                        echo sprintf($textTpl, $contentStr);
                    }else{
                        echo sprintf($picTpl,$this->articleCount,$this->items);
                    }
                }

        }else {
            echo "";
            exit;
        }
    }

    private function search(){

        global $wpdb;

        $q = new WP_Query;
        // Current maximum number of posts for weixin is 5. True?
        $weixin_posts = $q->query('s=' . $this->keyword . '&post_count=5');

        $items = '';

        foreach ($weixin_posts as $weixin_post ){

            $title = $weixin_post->post_title; 
            $excerpt = get_post_excerpt($weixin_post);//获取摘要
            $thumb = get_post_first_image($weixin_post->post_content);//获取缩略图;
            $link = get_permalink($weixin_post->ID);



            $items = $items . $this->get_item($title, $excerpt, $thumb, $link);
        }



        $this->articleCount = count($weixin_posts);
        if($this->articleCount > 5) $this->articleCount = 5;

        $this->items = $items;
    }

    private function get_item($title, $description, $picUrl, $url){
        if(!$description) $description = $title;

        return
        '
        <item>
            <Title><![CDATA['.$title.']]></Title>
            <Discription><![CDATA['.$description.']]></Discription>
            <PicUrl><![CDATA['.$picUrl.']]></PicUrl>
            <Url><![CDATA['.$url.']]></Url>
        </item>
        ';
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];    
                
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
}

if(!function_exists('get_post_excerpt')){

    function get_post_excerpt($post){
        $post_excerpt = strip_tags($post->post_excerpt); 
        if(!$post_excerpt){
            $post_excerpt = mb_substr(trim(strip_tags($post->post_content)),0,120);
        }
        return $post_excerpt;
    }

}

if(!function_exists('get_post_first_image')){

    function get_post_first_image($post_content){
        preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post_content, $matches);
        if($matches){       
            return $matches[1][0];
        }else{
            return false;
        }
    }

}
