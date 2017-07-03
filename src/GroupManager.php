<?php

namespace Guoxiangke\GroupManager;

use Hanson\Vbot\Extension\AbstractMessageHandler;

use Hanson\Vbot\Contact\Friends;
use Hanson\Vbot\Contact\Groups;
use Hanson\Vbot\Contact\Myself;
use Hanson\Vbot\Message\Card;
use Hanson\Vbot\Message\Text;
use Hanson\Vbot\Message\Image;
use Illuminate\Support\Collection;

class GroupManager extends AbstractMessageHandler
{

    public $author = 'Dale.Guo';

    public $version = '1.0';

    public $name = 'group_manager';

    public $zhName = '群主管理';

    private static $points = [];
    public static $status = true;

    private static $request_friend_log = [];
    private static $extension_config = [];

    public function handler(Collection $message)
    {
    	/** @var Friends $friends */
        $friends = vbot('friends');

        /** @var Groups $groups */
        $groups = vbot('groups');

        // 获取自己实例
        $myself = vbot('myself');

        //自动转发管理员@群名称发的消息给机器人，然后去掉@群名后转发到对应的群里。
        //TODO; 确定管理员标准按照昵称？
        //begin of 群管理
        $before_join_message = static::$extension_config['before_join_message'] ?: "现在自动拉你入群，进群后请\r\n☝看群公告\r\n✌设置消息免打扰";
        $welcome_join_message = static::$extension_config['welcome_join_message'] ?:"欢迎您加入本群[鼓掌]\r\n向大家[嘘]介绍一下自己吧[微笑]";
        $share_txt = static::$extension_config['share_txt'] ?:"请转发本图片到您朋友圈保留24小时即可获取播放地址，谢谢\r\n永不止息，感恩有你！\r\n如果已经发送的朋友，请您以基督徒的身份发送“已发朋友圈”给俺！";
        
        foreach ($groups as $groupUsername => $group) {
            //check must be 群主
            if( isset($group['IsOwner']) && !$group['IsOwner']) continue;

            //如果添加好友时填写包涵完整群名字！
            if ($message['type'] === 'request_friend'
                && strpos($message['info']['Content'], $group['NickName']) !== false) {//发出请求！
                // 全局记录请求的info：$message['info']['Content']
                $friends->approve($message);//同意请求
                $groups->addMember($groupUsername, $message['info']['UserName']);
                Text::send($message['info']['UserName'], $before_join_message);
                //完全相等！！！
                if($message['info']['Content'] == $group['NickName']){
                    static::$request_friend_log[$message['info']['UserName']] = $message['info'];
                    $share_filename = static::$extension_config['image_path'].$group['NickName'].'/share.jpg';
                    if(file_exists($share_filename)){
                        Image::send($message['info']['UserName'], $share_filename);
                        Text::send($message['info']['UserName'], $share_txt);
                        break;
                        // Text::send($gid, '⚠️播放链接即将私信发到您的微信，请注意查收!');
                    }
                }
            }//如果添加好友时填写包涵完整群名字！end
            //if 已发朋友圈 begin
            if (isset($message['pure'])
                && $message['type'] === 'text'
                && $message['pure']=== "已发朋友圈"
                && $message['fromType'] === 'Friend') {
                if(isset(static::$request_friend_log[$message['from']['UserName']])){
                    $group_nickname = static::$request_friend_log[$message['from']['UserName']]['Content'];//0CMYP
                    if(isset(static::$extension_config['groups'][$group_nickname]['resource'])){
                        $resource_return = static::$extension_config['groups'][$group_nickname]['resource'];
                        Text::send($message['from']['UserName'], $resource_return);return;
                    }else{
                        Text::send($message['from']['UserName'], "对不起，由于您没有按套路出牌，机器人听不懂啦！");return;
                    }
                }else{
                    Text::send($message['from']['UserName'], "对不起，由于您没有按套路出牌，机器人听不懂啦！");return;
                }
                
            }
            //if 已发朋友圈 end


            // begin 自动转发
            if (in_array($message['from']['NickName'], ['天空蔚蓝','xiaoyong','小永'])) {//bug TODO  set ［var］ name？！
                //check B if in AA?
                if(static::isUserInGroup($group, $message)) {
                    if(strrpos($message['content'], '@'.$group['NickName'], -strlen($message['content'])) !== false ){
                        $reg = '/'.preg_quote('@'.$group['NickName'], '/').'/';
                        $content = preg_replace($reg, '', $message['content'], 1);
                        Text::send($groupUsername,$content);
                        return;
                        //add message to DB
                        //end DB
                    }
                }

            }//end of 自动转发

            //如果和小永🤖️聊天信息包含群全名，if not in group!!自动加入群组
            if (isset($message['content']) 
                && strpos($message['content'], $group['NickName']) !== false
                && $message['fromType']=='Friend') {
                if(!static::isUserInGroup($group, $message)) {//if not in group!!
                    $groups->addMember($groupUsername, $message['from']['UserName']);
                    Text::send($message['from']['UserName'], $before_join_message);
                }else{
                    Text::send($message['from']['UserName'], '本话题已自动帮您转发到群里'.$group['NickName'].",有事儿咱们群里聊吧[握手]");
                    Text::send($groupUsername,$message['from']['NickName'].'发布了本群话题：'.$message['content']);
                }
            }


            //////begin!!//////
            if ($message['from']['NickName'] === $group['NickName']) {//'直播吧' && is admin!!!
                //管理员功能！！！
                //@sss 踢begin
                if(isset($message['pure']) && $message['pure'] == '踢' && $message['fromType']='Self' ) {
                    $pattern = '/@(\S+)\s*踢$/';
                    preg_match($pattern, $message['content'],$matches);
                    if (isset($matches[1])) {
                        if($uid = static::getUidByName($matches[1], $group)){
                            Text::send($groupUsername,$matches[0].'你即将被踢出群聊，再见👋');
                            $groups->deleteMember($groupUsername, $uid);
                        }
                    }
                }
                //@sss 踢end

                //@昵称＋＋ 积分！！begin
                if(isset($message['pure']) && in_array($message['pure'], ['[强]','[弱]'])) {
                    //初始化积分,默认100分，管理员每次－10分，else＋－1分，50分踢出群！
                    foreach ($group['MemberList'] as $member) {
                        if(!isset(static::$points[$group['NickName']][$member['UserName']])){
                            static::$points[$group['NickName']][$member['UserName']]= 100;
                        }
                    }
                    // if($member['UserName'] == $message['username']){
                        $points = 1;
                        if($message['fromType']=='Self' || (isset($message['sender']['RemarkName'])&&$message['sender']['RemarkName']=='代理群主')) $points=10;
                        $pattern = '/@(\S+)\s*\[/';//get nickname 小永
                        preg_match($pattern, $message['content'],$matches);
                        if(isset($matches[1])){
                            $memberNickname = trim($matches[1]);
                            $uid = static::getUidByName($memberNickname, $group);
                            if($uid){//如果@不是群内的人昵称，忽略！！！
                                if($message['pure'] =='[强]'){
                                    static::$points[$group['NickName']][$uid]+=$points;
                                    Text::send($groupUsername, '@'.$memberNickname." 恭喜您获得积分:  $points \r\n 您的积分: ".static::$points[$group['NickName']][$uid]);
                                }else{
                                    //不能减管理员的分数！或者说管理员不能退出群！！！
                                    // 代理群主也是－10分！
                                    static::$points[$group['NickName']][$uid]-=$points;
                                    if(static::$points[$group['NickName']][$uid]<60){
                                        if($uid != $message['from']['ChatRoomOwner']){
                                            Text::send($groupUsername, '@'.$memberNickname." 扣除积分:  $points \r\n 您的积分: ".static::$points[$group['NickName']][$uid]." \r\n 不及格，即将被踢出本群！再见👋");
                                            $groups->deleteMember($groupUsername, $member['UserName']);
                                            unset(static::$points[$group['NickName']][$uid]);
                                        }
                                    }else{
                                        Text::send($groupUsername, '@'.$memberNickname." 扣除积分:  $points \r\n 您的积分: ".static::$points[$group['NickName']][$uid]);
                                    }
                                }
                            }else{
                                Text::send($groupUsername, '@'.$memberNickname." 不在本群，请检查昵称再试！[撇嘴]");
                            }
                        }
                    // }
                    //++ --
                }//@昵称＋＋ 积分！！end
                if($message['content'] == '积分') {
                    //积分初始化；
                    if(!isset(static::$points[$group['NickName']])){
                        static::initGroupPoints($group);
                    }
                    foreach ($group['MemberList'] as $key => $member) {
                        $group['MemberList'][$key]['points'] = static::$points[$group['NickName']][$member['UserName']] ;
                        $points[$key] = static::$points[$group['NickName']][$member['UserName']] ;
                    }
                    array_multisort($points, SORT_DESC,  $group['MemberList']);
                    $i = 0; $tops = '';
                    foreach ($group['MemberList'] as $member) {
                        if($i++>5) break;
                        $tops .= $i.'、'.$member['NickName'] . '（' . $member['points']."）\r\n";
                    }
                    Text::send($groupUsername, "=====😇积分榜😇=====\r\n".$tops);
                }

                // 设置群名称 直播吧
                // 红包、转账提醒
                // 自定义回复
                // 特殊关键词触发事件
                // $experience = '欢迎加入直播体验群：点此访问直播页面！回复［名片］可获取小永🤖️名片；回复［公众号］可关注我们！';
                $rule = 'https://live.yongbuzhixi.com <点此访问直播页面！回复［名片］可获取小永🤖️名片；回复［关注］可关注我们！先感谢大家对永不止息的的支持，谢谢配合。';
                //处理文本消息！
                $content = $message['content'];
                if ($message['type'] === 'text') {
                    switch ($content) {
                        case '群规':
                            $content='xxx查看了群规则，棒棒哒👍';
                            Text::send($message['from']['UserName'], $rule);
                            break;
                        case '名片':
                            $content='xxx和小永交了好朋友，回复［名片］你也可哦😊';
                            Card::send($message['from']['UserName'], 'love_yongbuzhixi_com', '小永2');
                            break;
                        case '关注':
                            $content='xxx关注了永不止息，回复［关注］你也可哦😊';
                            Card::send($message['from']['UserName'], 'www_yongbuzhixi_com', '永不止息');
                            break;
                        default:
                            //自己不回复自己！
                            // vbot('console')->log('group_change:', '<pre>'.print_r($message,1));
                            // if($message['fromType'] !== 'Self')
                            //     Text::send($message['from']['UserName'], static::reply($message['pure'], $message['from']['UserName']));
                            break;
                    }
                }

                if ($message['type'] === 'group_change') {
                    if ($message['action'] === 'ADD') {
                        Text::send($message['from']['UserName'], '@' . $message['invited'] . ' ' . $welcome_join_message);
                    }
                }
                //other type with content!!!
            }
            //////end!!//////
        }//end of 群管理
    }

  /**
   * Current message user is in one group?
   * @param $group
   * @param \Illuminate\Support\Collection $message
   *
   * @return bool
   */
    public static function isUserInGroup($group, Collection $message){
        foreach ($group['MemberList'] as $member) {
            if($member['UserName'] == $message['from']['UserName']){
                return true;
            }
        }
        return false;
    }

  /**
   * @param $name
   * @param $group
   *
   * @return bool or String
   */
    public static function getUidByName($name, $group){
        $key = array_search($name, array_column($group['MemberList'], 'NickName'));
        if($key)  return $group['MemberList'][$key]['UserName'];
        return false;
    }

    // 积分初始化；
    public static function initGroupPoints($group){
        foreach ($group['MemberList'] as $member) {
            if(!isset(static::$points[$group['NickName']][$member['UserName']])){
                static::$points[$group['NickName']][$member['UserName']] = 100;
            }
        }
    }

    /**
     * 注册拓展时的操作.
     */
    public function register()
    {
        static::$extension_config = [
            'image_path' => static::$config['image_path'] ?? vbot('config')['user_path'].'groups/',
            'before_join_message' => static::$config['before_join_message'] ?? "现在自动拉你入群，进群后请\r\n☝看群公告\r\n✌设置消息免打扰",
            'welcome_join_message' => static::$config['welcome_join_message'] ?? "欢迎您加入本群[鼓掌]\r\n向大家[嘘]介绍一下自己吧[微笑]",
            'share_txt' =>static::$config['share_txt'] ?? "请转发本图片到您朋友圈保留24小时即可获取播放地址，谢谢\r\n永不止息，感恩有你！\r\n如果已经发送的朋友，请您以基督徒的身份发送“已发朋友圈”给俺！",
            'groups' => static::$config['groups'] ?? [],
        ];

    }
}
