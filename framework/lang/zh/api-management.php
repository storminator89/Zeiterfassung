<?php
return [
 'title'=>'管理 API', 'contract'=>'标准安装的 API 初始关闭。本地管理员可在首次设置完成后通过此处配置和切换，无需编辑环境文件或重启。',
 'enabled'=>'开启', 'disabled'=>'关闭', 'state'=>'运行许可', 'revision'=>'版本', 'key'=>'签名密钥',
 'key_missing'=>'尚未准备', 'key_ready'=>'元数据已准备；开启和使用时验证密钥', 'key_operator'=>'已有已准入的操作员配置；开启时验证密钥',
 'key_blocked'=>'私有状态未确认。不会自动修复、替换或重试。',
 'boundary'=>'开启后，有效认证的客户端可访问现有 API。关闭会阻止 API 访问，但不会删除或轮换密钥或令牌。重新开启后，尚未过期的令牌可能再次有效。账户停用和密码撤销仍独立生效。',
 'password'=>'您当前的密码', 'confirm_enable'=>'我确认开启或验证 API 运行许可。', 'confirm_disable'=>'我确认关闭 API。',
 'confirm_key'=>'我明确确认新建独立的私有 API 签名密钥。现有密钥不会被替换。',
 'enable'=>'验证并开启 API', 'enable_new'=>'新建 API 密钥并开启', 'disable'=>'关闭 API',
 'key_secret'=>'签名密钥永不显示。这不会创建个人 Bearer 令牌；客户端通过现有 API 登录。',
 'notice_enabled'=>'API 许可已确认：开启。', 'notice_disabled'=>'API 许可已确认：关闭。', 'notice_unchanged'=>'API 状态已确认。',
];
