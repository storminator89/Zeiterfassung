<?php
return [
 'title'=>'Manage API', 'contract'=>'The standard installation starts with the API off. Local administration can prepare and switch it here even after initial setup, without editing environment files or restarting.',
 'enabled'=>'On', 'disabled'=>'Off', 'state'=>'Operating permission', 'revision'=>'Revision', 'key'=>'Signing key',
 'key_missing'=>'Not prepared yet', 'key_ready'=>'Metadata prepared; key checked when enabling and on use', 'key_operator'=>'Existing admitted operator profile; key checked when enabling',
 'key_blocked'=>'Private state unconfirmed. No automatic repair, replacement or retry.',
 'boundary'=>'On exposes the existing API to valid authenticated clients. Off blocks API access but does not delete or rotate keys or tokens. Unexpired tokens may work again when enabled. Account suspension and password revocation remain independently effective.',
 'password'=>'Your current password', 'confirm_enable'=>'I confirm enabling or checking the API permission.', 'confirm_disable'=>'I confirm disabling the API.',
 'confirm_key'=>'I explicitly confirm creating a new independent private API signing key. Existing keys will not be replaced.',
 'enable'=>'Check and enable API', 'enable_new'=>'Create API key and enable', 'disable'=>'Disable API',
 'key_secret'=>'The signing key is never displayed. This does not create a personal bearer token; clients sign in through the existing API.',
 'notice_enabled'=>'API permission confirmed: On.', 'notice_disabled'=>'API permission confirmed: Off.', 'notice_unchanged'=>'API state confirmed.',
];
