# group-manager
群主管理拓展

1.如果请求加好友时，填写群名称，自动同意（有时需要手动！）添加群成员到对应群中。

2.如果和机器人🤖️聊天信息包含群全名且不在该群组内，自动加入群组。如果在群里，自动转发到群里！

3.自动转发管理员@群名称发送给机器人消息，然后去掉@群名后转发到对应的群里。

4.@群昵称 积分 初始化积分,默认100分，管理员每次－10分，else＋－1分，50分踢出群！


## 安装

```
composer require guoxiangke/vbot-group-manager
```

## 扩展属性

```php
name: group-manager
zhName: 群主管理
author: Dale.Guo
```

## 触发关键字

群主管理

## 配置项

无

## 扩展负责人

[Dale](https://github.com/guoxiangke)
