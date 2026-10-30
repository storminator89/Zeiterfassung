<?php

// Fixed own-local-password UI labels only; no credential input or model.
return [
    'title' => '更改自己的本地密码',
    'contract' => '仅限自己的本地账户。新密码须为12–72字节，不含控制字符，不会被截断。确认更改后将退出登录，必须重新登录。现有网页会话和API令牌在下一次密码版本校验时失效。不会更改LDAP或终端PIN，也不提供账户恢复。此表单不保存其他设置。',
    'current' => '当前密码',
    'new' => '新密码（12–72字节）',
    'confirm' => '确认新密码',
    'submit' => '更改密码并退出登录',
    'sessions_title' => '退出现有网页和 API 登录',
    'sessions_contract' => '仅适用于您本人的现有 Bcrypt 密码本地账户：确认后，现有网页会话和 API 令牌将在下次受保护检查时失效，包括当前会话。密码保持不变，仍可用于重新登录。不会追溯取消已在运行的请求。此操作不删除设备列表，也不撤销 LDAP、终端 PIN 或终端授权。不会保存其他设置。当前密码最多 72 字节，不能包含 NUL；较长的旧密码请先使用单独的密码修改表单更改。',
    'sessions_confirm' => '我确认撤销本人本地账户的所有现有网页和 API 登录，并在之后重新登录。',
    'sessions_submit' => '撤销登录并退出',
];
