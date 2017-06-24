<?php

namespace Guoxiangke\GroupManager;

use Hanson\Vbot\Extension\AbstractMessageHandler;

use Hanson\Vbot\Contact\Friends;
use Hanson\Vbot\Contact\Groups;
use Hanson\Vbot\Contact\Myself;
use Hanson\Vbot\Message\Card;
use Hanson\Vbot\Message\Text;
use Illuminate\Support\Collection;

class GroupManager extends AbstractMessageHandler
{

    public $author = 'Dale.Guo';

    public $version = '1.0';

    public $name = 'group_manager';

    public $zhName = '群主管理';
    public static $status = true;
    
    private static $array = [];

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
        foreach ($groups as $gid => $group) {
            //check must be 群主
            if( isset($group['IsOwner']) && !$group['IsOwner']) {
                continue;
            }
            // elseif( !isset($group['ChatRoomOwner']) || $group['ChatRoomOwner'] !== $myself->username) {
            //     continue;
            // }
            
            

            // begin 自动转发
            if (in_array($message['from']['NickName'], ['天空蔚蓝','xiaoyong','小永'])) {//bug TODO  set ［var］ name？！
                //check B if in AA?
                $is_ingroup = false;
                foreach ($group['MemberList'] as  $member) {
                    if($member['UserName'] == $message['from']['UserName']){
                        $is_ingroup = true;
                        break;
                    }
                }
                if($is_ingroup) {
                    if(strrpos($message['content'], '@'.$group['NickName'], -strlen($message['content'])) !== false ){
                        $reg = '/'.preg_quote('@'.$group['NickName'], '/').'/';
                        $content = preg_replace($reg, '', $message['content'], 1);
                        Text::send($gid,$content);

                        //add message to DB
                        //end DB
                        return;
                    }
                }
                
            }//end of 自动转发

            //如果添加好友时填写完整群名字！或拼音
            if ($message['type'] === 'request_friend') {
                // vbot('console')->log('收到好友申请:'.$message['info']['Content'].$message['avatar']);
                if ($message['info']['Content'] === $group['NickName']) {
                    $friends->approve($message);
                    Text::send($message['info']['UserName'], '亲，小永在这里等你很久了！感谢跟智能小永🤖️交朋友👬，永不止息，需要有你！');
                    $groups->addMember($gid, $message['info']['UserName']);//$groups->getUsernameByNickname('直播吧')
                    Text::send($message['info']['UserName'], '现在拉你进去群，记得设置免骚扰哦！😊');

                }
            }//end of 自动通过添加好友并入群，根据群名

            //如果和小永🤖️聊天信息包含群全名，自动加入群组
            if (strpos($message['content'], $group['NickName']) !== false) {
                $groups->addMember($gid, $message['from']['UserName']);
                Text::send($message['from']['UserName'], '现在拉你进去群，记得设置免骚扰哦！');
            }

            //////begin!!//////
            // vbot('console')->log($group['NickName'],'<pre>'.print_r($message,1));
            // vbot('console')->log($gid,$group['NickName']);
            if ($message['from']['NickName'] === $group['NickName']) {//'直播吧' && is admin!!!
                //管理员功能！！！//@sss 踢                    // @人+暗号踢人出去：
                if(isset($message['pure']) && $message['pure'] == '踢' ) {//&& $message['isAt']
                    if (in_array($message['sender']['NickName'], ['xiaoyong','小永','天空蔚蓝'])) {// have bug
                        $pattern = '/@(\S+) 踢/';
                        preg_match($pattern, $message['content'],$matches);
                        //vbot('console')->log($message['content'],'<pre>'.print_r($matches,1));
                        if (isset($matches[1])) {
                            Text::send($gid,$matches[0].'你即将被踢出群了');
                            // $groups->deleteMember($gid, $message['from']['UserName']);
                        } 
                    }
                }
                // 设置群名称 直播吧
                // 红包、转账提醒
                // 自定义回复
                // 特殊关键词触发事件
                // $experience = '欢迎加入直播体验群：点此访问直播页面！回复［名片］可获取小永🤖️名片；回复［公众号］可关注我们！';
                $rule = 'https://live.yongbuzhixi.com <点此访问直播页面！回复［名片］可获取小永🤖️名片；回复［公众号］可关注我们！先感谢大家对永不止息的的支持，谢谢配合。';
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
                        Text::send($message['from']['UserName'], '欢迎新人 '.$message['invited'] ."[鼓掌]");
                    }
                }
                //other type with content!!!
            }
            //////end!!//////
        }//end of 群管理
    }

    /**
     * 注册拓展时的操作.
     */
    public function register()
    {

    }
}