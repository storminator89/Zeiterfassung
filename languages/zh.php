<?php
define('TITLE', 'Quodara Chrono - 時間追踪');
define('NAV_HOME', '首頁');
define('NAV_DASHBOARD', '儀表板');
define('NAV_SETTINGS', '設置');
define('NAV_ABOUT', '關於');
define('NAV_LOGOUT', '登出');
define('NAV_DARK_MODE', '黑暗模式');
define('NAV_ADMIN', '管理員');
define('FORM_START_TIME', '開始時間');
define('FORM_END_TIME', '結束時間');
define('SETTINGS_TITLE', '設定'); 
define('LANGUAGE_SELECTION', '選擇語言');
define('IMPORT_DATABASE', '導入數據庫');
define('DOWNLOAD_DATABASE', '下載數據庫');
define('FORM_COME', '簽到');
define('FORM_GO', '簽退');
define('LABEL_HOURS', '小時');
define('LABEL_MINUTES', '分鐘');
define('FORM_BREAK_MANUAL', '休息（手動）');
define('FORM_BREAK_MINUTES', '休息（分鐘）');
define('FORM_START_BREAK', '開始/結束休息');
define('FORM_LOCATION', '地點');
define('FORM_COMMENT', '註記');
define('BUTTON_PAUSE_START', '開始休息');
define('BUTTON_PAUSE_RESUME', '繼續休息');
define('BUTTON_PAUSE_END', '結束休息');
define('LOCATION_OFFICE', '辦公室');
define('LOCATION_HOME_OFFICE', '家庭辦公室');
define('LOCATION_BUSINESS_TRIP', '商務旅行');
define('LOCATION_OFFICE_VALUE', '辦公室');
define('LOCATION_HOME_OFFICE_VALUE', '家庭辦公室');
define('LOCATION_BUSINESS_TRIP_VALUE', '商務旅行');
define('FORM_EVENT_TYPE', '事件類型');
define('EVENT_VACATION', '假期');
define('EVENT_HOLIDAY', '國定假日');
define('EVENT_SICK', '生病');
define('FORM_START', '開始');
define('FORM_END', '結束');
define('BUTTON_SUBMIT_DATA', '提交數據');
define('BUTTON_ADD_BOOKING', '添加預訂');
define('BUTTON_DELETE_SELECTED', '刪除選定的');
define('FOOTER_TEXT', '© 2024 Quodara Chrono - 時間追踪');
define('ABOUT_TOOL_TEXT', '該工具有助於時間追踪');
define('BUTTON_DOWNLOAD_BACKUP', '下載數據庫備份');
define('BUTTON_IMPORT_DB', '導入數據庫');
define('BUTTON_CLOSE', '關閉');
define('BUTTON_IMPORT', '導入');
define('STATISTICS_WORKING_TIMES', '工作時間統計');
define('TABLE_HEADER_ID', 'ID');
define('TABLE_HEADER_WEEK', '周');
define('TABLE_HEADER_START_TIME', '開始時間');
define('TABLE_HEADER_END_TIME', '結束時間');
define('TABLE_HEADER_DURATION', '持續時間');
define('TABLE_HEADER_BREAK', '休息（分鐘）');
define('TABLE_HEADER_LOCATION', '地點');
define('TABLE_HEADER_COMMENT', '評論');
define('TABLE_HEADER_WORKING_DAYS', '工作日');
define('TABLE_HEADER_TOTAL_OVERTIME', '總加班時間');
define('HOLIDAYS_THIS_WEEK', '本週假期');
define('HOLIDAYS', '假期');
define('TABLE_HEADER_DATE', '日期');
define('TABLE_HEADER_DAY', '日');
define('TABLE_HEADER_NAME', '名稱');
define('ACTUAL_WORKED_TIMES', '實際工作時間');
define('BUTTON_TODAY', '今天');
define('MODAL_TITLE_SCHEDULE', '工作時間');
define('MODAL_BUTTON_CLOSE', '關閉');
define('BUTTON_PREV_MONTH', '上個月');
define('BUTTON_NEXT_MONTH', '下個月');

// Months
define('MONTH_JANUARY', '一月');
define('MONTH_FEBRUARY', '二月');
define('MONTH_MARCH', '三月');
define('MONTH_APRIL', '四月');
define('MONTH_MAY', '五月');
define('MONTH_JUNE', '六月');
define('MONTH_JULY', '七月');
define('MONTH_AUGUST', '八月');
define('MONTH_SEPTEMBER', '九月');
define('MONTH_OCTOBER', '十月');
define('MONTH_NOVEMBER', '十一月');
define('MONTH_DECEMBER', '十二月');

// Admin Page
define('ADMIN_PAGE_TITLE', '管理區域 - 用戶管理');
define('USER_MANAGEMENT_TITLE', '用戶管理');
define('FORM_USERNAME', '用戶名');
define('FORM_PASSWORD', '密碼');
define('FORM_EMAIL', '電子郵件');
define('FORM_ROLE_USER', '用戶');
define('FORM_ROLE_ADMIN', '管理員');
define('FORM_SEARCH_USER', '搜索用戶...');
define('EXISTING_USERS_TITLE', '現有用戶');
define('TABLE_HEADER_ACTIONS', '操作');
define('BUTTON_CREATE_USER', '創建用戶');
define('BUTTON_EDIT', '編輯');
define('BUTTON_DELETE', '刪除');
define('BUTTON_SAVE_CHANGES', '保存更改');
define('BUTTON_GENERATE_TOKEN', '生成令牌');
define('BUTTON_API_DOC', 'API文檔');
define('CONFIRM_DELETE_USER', '你確定要刪除此用戶嗎？');
define('MODAL_TITLE_SUCCESS', '成功');
define('MODAL_TITLE_ERROR', '錯誤');
define('MODAL_TITLE_EDIT_USER', '編輯用戶');
define('TOKEN_GENERATED_SUCCESS', '令牌生成並成功保存！');
define('TOKEN_GENERATED_ERROR', '保存令牌時出錯：');
define('ERROR_ALL_FIELDS_REQUIRED', '所有字段都是必需的！');
define('USER_CREATED_SUCCESS', '用戶創建成功！');
define('USER_CREATED_ERROR', '創建用戶時出錯：');
define('USER_DELETED_SUCCESS', '用戶刪除成功！');
define('USER_DELETED_ERROR', '刪除用戶時出錯：');
define('USER_UPDATED_SUCCESS', '用戶更新成功！');
define('USER_UPDATED_ERROR', '更新用戶時出錯：');
define('API_ACCESS_TITLE', 'API - 訪問');
define('FORM_NEW_PASSWORD', '新密碼（可選）');
define('TABLE_HEADER_USERNAME', '用戶名');
define('TABLE_HEADER_EMAIL', '電子郵件');
define('TABLE_HEADER_ROLE', '角色');

//Login page
define('LOGIN_TITLE', '登錄 - Quodara Chrono');
define('LOGIN_PAGE_TITLE', '登錄');
define('USERNAME_LABEL', '用戶名');
define('PASSWORD_LABEL', '密碼');
define('LOGIN_BUTTON', '登錄');
define('REGISTER_BUTTON', '註冊');
define('SUCCESS_MODAL_TITLE', '成功');
define('ERROR_MODAL_TITLE', '錯誤');
define('ERROR_INVALID_CSRF', '無效的CSRF令牌。');
define('ERROR_INVALID_CREDENTIALS', '無效的用戶名或密碼！');
define('ERROR_EXISTING_USER', '由於用戶已存在，註冊不可能。');
define('SUCCESS_REGISTRATION', '註冊成功！你現在可以登錄了。');

?>
