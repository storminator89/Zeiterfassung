<?php

// Fixed own-local-password UI labels only; no credential input or model.
return [
    'title' => '更改自己的本地密码',
    'contract' => '仅限自己的本地账户。新密码须为12–72字节，不含控制字符，不会被截断。确认更改后将退出登录，必须重新登录。现有网页会话和API令牌在下一次密码版本校验时失效。不会更改LDAP或终端PIN，也不提供账户恢复。此表单不保存其他设置。',
    'current' => '当前密码',
    'new' => '新密码（12–72字节）',
    'confirm' => '确认新密码',
    'submit' => '更改密码并退出登录',
];
