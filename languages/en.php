<?php
define('TITLE', 'Quodara Chrono - Time Tracking');
define('NAV_HOME', 'Home');
define('NAV_DASHBOARD', 'Dashboard');
define('NAV_SETTINGS', 'Settings');
define('NAV_ABOUT', 'About');
define('NAV_LOGOUT', 'Logout');
define('NAV_DARK_MODE', 'Dark Mode');
define('NAV_ADMIN', 'Admin');
define('NAV_SUPERVISOR', 'Supervisor Overview');
define('NAV_MAIN_MENU', 'Main menu');
define('NAV_SECTION_WORK', 'Working time');
define('NAV_SECTION_TEAM', 'Team');
define('NAV_SECTION_ADMIN', 'Administration');
define('NAV_SECTION_ACCOUNT', 'Account & help');
define('NAV_MENU_CLOSE', 'Close menu');
define('SETTINGS_TITLE', 'Settings');
define('SETTINGS_THEME_LIGHT', 'Light');
define('SETTINGS_THEME_DARK', 'Dark');
define('SETTINGS_THEME_SYSTEM', 'System');
define('LANGUAGE_SELECTION', 'Select Language');
define('IMPORT_DATABASE', 'Import Database');
define('DOWNLOAD_DATABASE', 'Database Backup');
define('FORM_START_TIME', 'Start Time');
define('FORM_END_TIME', 'End Time');
define('FORM_COME', 'Arrive');
define('FORM_GO', 'Leave');
define('LABEL_HOURS', 'Hours');
define('LABEL_MINUTES', 'Minutes');
define('FORM_BREAK_MANUAL', 'Break (manual)');
define('FORM_BREAK_MINUTES', 'Break (minutes)');
define('FORM_START_BREAK', 'Start/End Break');
define('FORM_LOCATION', 'Location');
define('FORM_COMMENT', 'Comment');
define('BUTTON_PAUSE_START', 'Start Break');
define('BUTTON_PAUSE_RESUME', 'Resume Break');
define('BUTTON_PAUSE_END', 'End Break');
define('LOCATION_OFFICE', 'Office');
define('LOCATION_HOME_OFFICE', 'Home Office');
define('LOCATION_BUSINESS_TRIP', 'Business Trip');
define('LOCATION_OFFICE_VALUE', 'Office');
define('LOCATION_HOME_OFFICE_VALUE', 'Home Office');
define('LOCATION_BUSINESS_TRIP_VALUE', 'Business Trip');
define('FORM_EVENT_TYPE', 'Event Type');
define('EVENT_VACATION', 'Vacation');
define('EVENT_HOLIDAY', 'Holiday');
define('EVENT_SICK', 'Sick');
define('FORM_START', 'Start');
define('FORM_END', 'End');
define('BUTTON_SUBMIT_DATA', 'Submit');
define('BUTTON_ADD_BOOKING', 'Book');
define('BUTTON_DELETE_SELECTED', 'Delete Selected');
define('FOOTER_TEXT', '© 2024 Quodara Chrono - Time Tracking');
define('ABOUT_TOOL_TEXT', 'This tool helps with time tracking');
define('BUTTON_DOWNLOAD_BACKUP', 'Download Database Backup');
define('BUTTON_IMPORT_DB', 'Import Database');
define('BUTTON_CLOSE', 'Close');
define('BUTTON_IMPORT', 'Import');
define('STATISTICS_WORKING_TIMES', 'Working Time Statistics');
define('TABLE_HEADER_ID', 'ID');
define('TABLE_HEADER_WEEK', 'CW');
define('TABLE_HEADER_START_TIME', 'Start Time');
define('TABLE_HEADER_END_TIME', 'End Time');
define('TABLE_HEADER_DURATION', 'Duration');
define('TABLE_HEADER_BREAK', 'Break (minutes)');
define('TABLE_HEADER_LOCATION', 'Location');
define('TABLE_HEADER_COMMENT', 'Comment');
define('TABLE_HEADER_WORKING_DAYS', 'Working Days');
define('TABLE_HEADER_TOTAL_OVERTIME', 'Total Overtime');
define('HOLIDAYS_THIS_WEEK', 'Holidays This Week');
define('HOLIDAYS', 'Holidays');
define('TABLE_HEADER_DATE', 'Date');
define('TABLE_HEADER_DAY', 'Day');
define('TABLE_HEADER_NAME', 'Name');
define('TABLE_HEADER_REGULAR_WORKING_HOURS', 'Daily Working Hours');
define('LABEL_HOURS_PER_DAY', 'Hours per Day');
define('ACTUAL_WORKED_TIMES', 'Actual Worked Times');
define('MODAL_TITLE_SCHEDULE', 'Work Schedule');
define('MODAL_BUTTON_CLOSE', 'Close');
define('BUTTON_PREV_MONTH', 'Previous Month');
define('BUTTON_NEXT_MONTH', 'Next Month');

// Months
define('MONTH_JANUARY', 'January');
define('MONTH_FEBRUARY', 'February');
define('MONTH_MARCH', 'March');
define('MONTH_APRIL', 'April');
define('MONTH_MAY', 'May');
define('MONTH_JUNE', 'June');
define('MONTH_JULY', 'July');
define('MONTH_AUGUST', 'August');
define('MONTH_SEPTEMBER', 'September');
define('MONTH_OCTOBER', 'October');
define('MONTH_NOVEMBER', 'November');
define('MONTH_DECEMBER', 'December');
define('FORM_DESCRIPTION', 'Description');
define('LATEST_TIME_RECORD', 'Latest Time Entry');
define('DASHBOARD_TITLE', 'Dashboard');
define('DASHBOARD_PROGRESS', 'Progress');
define('DASHBOARD_CURRENT_WEEK', 'Current Week %s');
define('DASHBOARD_HOURS_OF', '%s of %s hours');
define('DASHBOARD_CALENDAR', 'Calendar');
define('DASHBOARD_OVERTIME_INFO', 'Carry-in plus work/absence credits minus target per completed booking start-day. Total: all stored days; as-of: only through the Berlin date. Missing target days are not deducted; not a complete contractual account.');
define('DASHBOARD_WEEKLY_PRODUCTIVITY_INFO', 'Work plus absence credits divided by the full weekly target, capped at 0–100%. No positive target: 0%. Not a productivity rating.');
define('DASHBOARD_PRODUCTIVITY_SCORE_INFO', 'Work plus absence credits divided by the full monthly target, capped at 0–100%. No positive target: 0%. Not a productivity rating.');
define('BUTTON_PREV_WEEK', 'Previous Week');
define('BUTTON_NEXT_WEEK', 'Next Week');
define('BUTTON_TODAY', 'Today');
define('USER_INFO', 'User Information');
define('USERNAME', 'Username');
define('EMAIL', 'Email');
define('DEPARTMENT', 'Department');
define('REGULAR_WORKING_HOURS', 'Regular Working Hours');
define('OVERTIME', 'Overtime');
define('HOURS', 'Hours');
define('RECENT_TIME_ENTRIES', 'Recent Time Entries');
define('START_TIME', 'Start Time');
define('END_TIME', 'End Time');
define('DESCRIPTION', 'Description');
define('LOCATION', 'Location');
define('PAUSE_SETTINGS', 'Break Settings');
define('PAUSE_RULE', 'For %s hours of work: At least %s minutes break');

// Weekly Overview
define('DASHBOARD_WEEKLY_OVERVIEW', 'Weekly Overview');
define('DASHBOARD_WEEKLY_HOURS', 'Weekly Hours');
define('DASHBOARD_REMAINING_HOURS_WEEK', 'Left to weekly target');

// Monthly Overview
define('DASHBOARD_MONTHLY_OVERVIEW', 'Monthly Overview');
define('DASHBOARD_MONTHLY_HOURS', 'Monthly Hours');
define('DASHBOARD_AVERAGE_DAILY_HOURS', 'Average per booking day');
define('DASHBOARD_PRODUCTIVITY_SCORE', 'Monthly target coverage');
define('DASHBOARD_WORKED_DAYS_THIS_MONTH', 'From %d completed booking days');
define('DASHBOARD_WEEKLY_PRODUCTIVITY', 'Weekly target coverage');
define('DASHBOARD_MONTHLY_HOURS_PROGRESS', '%s of %s full monthly target');

// Overall Statistics
define('DASHBOARD_OVERALL_STATS', 'Overall Statistics');
define('DASHBOARD_REGULAR_HOURS', 'Regular Working Hours');
define('DASHBOARD_HOURS_PER_DAY', 'Hours per Day');
define('DASHBOARD_WEEKS_WORKED', 'Weeks with bookings');
define('DASHBOARD_FIRST_WEEK', 'First booking week');
define('DASHBOARD_KEEP_GOING', 'Completed bookings count.');
define('DASHBOARD_TOTAL_OVERTIME', 'Recorded-day balance (all)');
define('DASHBOARD_RECORDED_AS_OF', 'Recorded booking days through %s (Europe/Berlin): %s');
define('DASHBOARD_OVER_HOURS_YEAR', 'Overtime This Year: %s');

// Days of the Week
define('SUNDAY', 'Sunday');
define('MONDAY', 'Monday');
define('TUESDAY', 'Tuesday');
define('WEDNESDAY', 'Wednesday');
define('THURSDAY', 'Thursday');
define('FRIDAY', 'Friday');
define('SATURDAY', 'Saturday');

// Event Types
define('WORK', 'Work');
define('VACATION', 'Vacation');

// Admin Page
define('ADMIN_PAGE_TITLE', 'Administration Area');
define('USER_MANAGEMENT_TITLE', 'User Management');
define('BUTTON_CREATE_USER', 'Create User');
define('FORM_USERNAME', 'Username');
define('FORM_PASSWORD', 'Password');
define('FORM_EMAIL', 'Email');
define('FORM_ROLE', 'Role');
define('FORM_ROLE_USER', 'User');
define('FORM_ROLE_ADMIN', 'Administrator');
define('FORM_ROLE_SUPERVISOR', 'Supervisor');
define('FORM_DEPARTMENT', 'Department');
define('FORM_SUPERVISOR', 'Supervisor');
define('FORM_NO_SUPERVISOR', 'No Supervisor');
define('TABLE_HEADER_USERNAME', 'Username');
define('TABLE_HEADER_EMAIL', 'Email');
define('TABLE_HEADER_ROLE', 'Role');
define('TABLE_HEADER_DEPARTMENT', 'Department');
define('TABLE_HEADER_SUPERVISOR', 'Supervisor');
define('TABLE_HEADER_ACTIONS', 'Actions');
define('BUTTON_EDIT', 'Edit');
define('BUTTON_DELETE', 'Delete');
define('CONFIRM_DELETE_USER', 'Are you sure you want to delete this user?');
define('ADMIN_USER_DELETE_HISTORY_LOCKED', 'Deletion blocked: accounts with history are retained.');
define('DEPARTMENT_MANAGEMENT_TITLE', 'Department Management');
define('FORM_NEW_DEPARTMENT', 'New Department');
define('BUTTON_ADD_DEPARTMENT', 'Add Department');
define('TABLE_HEADER_DEPARTMENT_ID', 'Department ID');
define('TABLE_HEADER_DEPARTMENT_NAME', 'Department Name');
define('CONFIRM_DELETE_DEPARTMENT', 'Are you sure you want to delete this department?');
define('LDAP_SYNC_TITLE', 'LDAP Synchronization');
define('FORM_LDAP_HOST', 'LDAP Host');
define('FORM_LDAP_PORT', 'LDAP Port');
define('FORM_LDAP_USER', 'LDAP User');
define('FORM_LDAP_PASS', 'LDAP Password');
define('FORM_LDAP_BASE_DN', 'LDAP Base DN');
define('BUTTON_SYNC_LDAP', 'Sync LDAP');
define('PAUSE_SETTINGS_TITLE', 'Break Settings');
define('TABLE_HEADER_HOURS_THRESHOLD', 'Hours Threshold');
define('TABLE_HEADER_MINIMUM_PAUSE', 'Minimum Break');
define('BUTTON_ADD_ROW', 'Add Row');
define('BUTTON_SAVE_CHANGES', 'Save Changes');
define('API_ACCESS_TITLE', 'API Access');
define('BUTTON_GENERATE_TOKEN', 'Generate Token');
define('BUTTON_API_DOC', 'API Documentation');
define('MODAL_TITLE_EDIT_USER', 'Edit User');
define('FORM_REGULAR_WORKING_HOURS', 'Regular Working Hours');
define('FORM_OVERTIME', 'Overtime');
define('FORM_NEW_PASSWORD', 'New Password (leave blank for no change)');
define('BUTTON_CANCEL', 'Cancel');
define('SEARCH_USERS', 'Search Users');
define('SEARCH_DEPARTMENTS', 'Search Departments');
define('MODAL_TITLE_EDIT_DEPARTMENT', 'Edit Department');
define('FORM_DEPARTMENT_NAME', 'Department Name');
define('TOKEN_GENERATED_SUCCESS', 'Token generated successfully.');
define('TOKEN_GENERATED_ERROR', 'Error generating token: ');
define('LDAP_SYNC_SUCCESS', 'LDAP synchronization completed successfully.');
define('LDAP_BIND_FAILED', 'LDAP bind failed.');
define('LDAP_CONNECTION_FAILED', 'LDAP connection failed.');
define('LDAP_EXTENSION_REQUIRED', 'The PHP LDAP extension is missing. The LDAP configuration was not changed.');
define('LDAP_SETTINGS_INVALID', 'Enter valid LDAP connection details including a user, password and port (1–65535). The LDAP configuration was not changed.');
define('LDAP_TLS_FAILED', 'The required LDAP TLS test failed. The LDAP configuration was not changed.');
define('LDAP_SEARCH_FAILED', 'The LDAP search test did not return a complete valid result. The LDAP configuration was not changed.');
define('LDAP_SYNC_FAILED', 'The validated LDAP configuration could not be saved or the import could not be completed. Check the saved state.');
define('DATABASE_CONNECTION_FAILED', 'Database connection failed: ');
define('DEPARTMENT_DELETED_SUCCESS', 'Department deleted successfully.');
define('DEPARTMENT_DELETED_ERROR', 'Error deleting department: ');
define('ERROR_ALL_FIELDS_REQUIRED', 'Please fill in all fields.');
define('DEPARTMENT_CREATED_SUCCESS', 'Department created successfully.');
define('DEPARTMENT_CREATED_ERROR', 'Error creating department: ');
define('USER_CREATED_SUCCESS', 'User created successfully.');
define('USER_CREATED_ERROR', 'Error creating user: ');
define('USER_DELETED_SUCCESS', 'User deleted successfully.');
define('USER_DELETED_ERROR', 'Error deleting user: ');
define('USER_UPDATED_SUCCESS', 'User updated successfully.');
define('USER_UPDATED_ERROR', 'Error updating user: ');
define('DEPARTMENT_UPDATED_SUCCESS', 'Department updated successfully.');
define('DEPARTMENT_UPDATED_ERROR', 'Error updating department: ');

//Login page
define('LOGIN_TITLE', 'Login - Quodara Chrono');
define('LOGIN_PAGE_TITLE', 'Login');
define('USERNAME_LABEL', 'Username');
define('PASSWORD_LABEL', 'Password');
define('LOGIN_BUTTON', 'Login');
define('REGISTER_BUTTON', 'Register');
define('SUCCESS_MODAL_TITLE', 'Success');
define('ERROR_MODAL_TITLE', 'Error');
define('ERROR_INVALID_CSRF', 'Invalid CSRF token.');
define('ERROR_INVALID_CREDENTIALS', 'Username or password incorrect!');
define('ERROR_EXISTING_USER', 'Registration is not possible as a user already exists.');
define('SUCCESS_REGISTRATION', 'Registration successful! You can now log in.');

//Supervisor Page
define('SUPERVISOR_TIMES_TITLE', 'Time Tracking Overview for Supervisors');
define('NOT_SUPERVISOR_MESSAGE', 'You are not a supervisor and do not have permission to view this page.');
define('NO_ENTRIES_FOUND', 'No entries found.');
define('TABLE_HEADER_OVERTIME', 'Overtime');
define('TOTAL_OVERTIME_TITLE', 'Total Overtime');

// Add these new constants for supervisor view
define('TABLE_HEADER_TOTAL_DURATION', 'Total Duration');
define('TABLE_HEADER_TOTAL_BREAK', 'Total Break');

//Settings Page
define('SETTINGS_LANGUAGE', 'Language Settings');
define('SETTINGS_REGULAR_WORKING_HOURS', 'Regular Working Hours');
define('SETTINGS_IMPORT_DATABASE', 'Import Database');
define('SETTINGS_DOWNLOAD_DATABASE', 'Download Database');
define('BUTTON_SAVE_REGULAR_WORKING_HOURS', 'Save Hours');
define('SETTINGS_OVERTIME', 'Previous Overtime');
define('SETTINGS_BACKUP_OPERATIONS_TITLE', 'Backup and restore');
define('SETTINGS_BACKUP_RESTORE_TITLE', 'Offline restoration');
define('SETTINGS_BACKUP_IMPORT_DISABLED', 'Browser import is intentionally disabled: a partial import could damage work-time models and change history.');
define('SETTINGS_BACKUP_RESTORE_INSTRUCTIONS', 'The entire private data directory (database, any SQLite sidecars and .key files) must be restored together offline with the app and device services stopped. The schema and code must match the backup.');
define('SETTINGS_BACKUP_RESTORE_GUIDE', 'This interface does not provide automatic full restoration. Offline procedure for operators:');
define('SETTINGS_BACKUP_SNAPSHOT_TITLE', 'Database snapshot');
define('SETTINGS_BACKUP_SNAPSHOT_DESCRIPTION', 'Consistent snapshot of the entire SQLite database. External .key files, application code and operational configuration are not included. This download is therefore not a complete operational backup.');
define('SETTINGS_BACKUP_SNAPSHOT_DOWNLOAD', 'Download DB snapshot');

// ...existing code...
define('TOUR_START', 'Start Tour');
// Table Headers
define('TABLE_HEADER_TIME_RECORDS', 'Time Records');
// ...existing code...

define('BUTTON_EXPORT_CSV', 'CSV Export');


// Native supervisor diagnostic fallback; source values are never repaired.
define('SUPERVISOR_INVALID_RECORDS', 'Data quality: %d invalid time entries.');
define('SUPERVISOR_TOTALS_UNAVAILABLE', 'This report contains no final totals. The affected person must clarify the original entries through the existing correction process with subsequent four-eyes approval. No times have been deleted or shortened.');
define('SUPERVISOR_SOURCE_VALUES', 'Source values – report unavailable. This view shows up to 10 source rows per page instead of daily groups.');
define('SUPERVISOR_INVALID_ROW', 'Invalid time entry');

// Same selected team-status labels as the existing Laravel supervisor card.
define('TEAM_STATUS_TITLE', 'Current team clocking status');
define('TEAM_STATUS_SNAPSHOT_NOTE', 'Server snapshot; not a live or online-presence signal:');
define('TEAM_STATUS_STATE', 'Clocking status');
define('TEAM_STATUS_WORKING', 'Working');
define('TEAM_STATUS_PAUSED', 'On break');
define('TEAM_STATUS_ABSENT', 'Not present');
define('TEAM_STATUS_UNKNOWN', 'Status unknown');
define('TEAM_STATUS_EMPTY', 'No assigned team members.');
define('TEAM_STATUS_UNAVAILABLE', 'Unavailable. Please reload the page.');

define('DASHBOARD_COMPLETED_WORK', 'Work: %s');
define('DASHBOARD_ABSENCE_CREDIT', 'Absence credits: %s');
define('DASHBOARD_REPORT_UNRESOLVED', 'Needs review');
define('DASHBOARD_REPORT_INCOMPLETE', 'Incomplete');
define('DASHBOARD_REPORT_REVIEW_HINT', 'Please review the bookings manually.');
define('DASHBOARD_CURRENT_PERIOD_NOTE', 'Week (%s–%s) and month (%s–%s): full current periods, not a to-date balance. Calendar navigation does not change these figures.');
// Read-only native holiday inventory; never an attestation of completeness.
define('HOLIDAY_INVENTORY_TITLE', 'Check stored public holidays');
define('HOLIDAY_INVENTORY_UNVERIFIED', 'Completeness unverified');
define('HOLIDAY_INVENTORY_ACTION', 'Before recording absences or using target hours, compare the stored dates for the relevant year and practice location with your administrator.');
define('HOLIDAY_INVENTORY_LIMIT', 'The stored data does not establish its region, source or completeness. The existing automatic source is Baden-Württemberg. This view only shows stored data; it does not import a year or change dates.');
define('HOLIDAY_INVENTORY_YEAR', 'Year');
define('HOLIDAY_INVENTORY_SHOW', 'Show stored dates');
define('HOLIDAY_INVENTORY_INVALID_YEAR', 'Invalid year. The current year is shown; please choose a year from 1 to 9999.');
define('HOLIDAY_INVENTORY_COUNT', '%d stored entries shown – not evidence of completeness.');
define('HOLIDAY_INVENTORY_DATA_ISSUE', 'Invalid dates or more than 366 entries: the display is limited; ask your administrator to review the original data.');
define('HOLIDAY_INVENTORY_EMPTY', 'No entry is stored for this year. This does not mean there are no public holidays.');
define('HOLIDAY_INVENTORY_ABSENCE_LINK', 'Before crediting an absence: check the stored holidays for the relevant year (new tab).');

define('SETTINGS_OWN_PASSWORD_TITLE', 'Change your own password');
define('SETTINGS_OWN_PASSWORD_HELP', 'Your own local password only; new: 12–72 bytes, no control characters. Success signs you out. Old web sessions and API tokens fail their next check; device PINs stay unchanged.');
define('SETTINGS_OWN_PASSWORD_CURRENT', 'Current password');
define('SETTINGS_OWN_PASSWORD_NEW', 'New password');
define('SETTINGS_OWN_PASSWORD_CONFIRM', 'Repeat new password');
define('SETTINGS_OWN_PASSWORD_SUBMIT', 'Change password and sign out');
define('SETTINGS_OWN_PASSWORD_UNAVAILABLE', 'Own local accounts only. Manage directory passwords in the directory.');
define('SETTINGS_OWN_PASSWORD_INVALID_FORM', 'Check password fields: new 12–72 bytes, no control characters, matching confirmation and different from the current password. Do not include other settings.');
define('SETTINGS_OWN_PASSWORD_INVALID_CURRENT', 'Check the current and new passwords.');
define('SETTINGS_OWN_PASSWORD_RATE_LIMITED', 'Too many attempts. Try again after ten minutes.');
define('SETTINGS_OWN_PASSWORD_STALE', 'Session no longer valid. Please sign in again.');
define('SETTINGS_OWN_PASSWORD_UNCONFIRMED', 'Password change not confirmed. Do not retry automatically. Check your sign-in status.');
define('SETTINGS_OWN_PASSWORD_LOGOUT_UNCONFIRMED', 'Password changed; complete sign-out not confirmed. Please sign in again.');
define('SETTINGS_OWN_PASSWORD_PROFILE_UNAVAILABLE', 'Password changes are not admitted for this data profile.');

// Existing generic team-absence labels; never expose type or notes.
define('TEAM_ABSENCE_TITLE', 'Recorded team absence days');
define('TEAM_ABSENCE_NOTE', 'Only stored absence days in the current team, without type or notes. Not a request, substitution plan or promise of available capacity.');
define('TEAM_ABSENCE_DATE', 'Date in week');
define('TEAM_ABSENCE_SHOW', 'Show week');
define('TEAM_ABSENCE_NAV', 'Team absence week');
define('TEAM_ABSENCE_PREVIOUS', 'Previous week');
define('TEAM_ABSENCE_TODAY', 'Current week');
define('TEAM_ABSENCE_NEXT', 'Next week');
define('TEAM_ABSENCE_INCOMPLETE', 'Some records are unclear or the read limit was reached. Missing markers do not prove availability.');
define('TEAM_ABSENCE_LEGEND', 'Absent = stored absence day; — = no displayed absence day, not proof of attendance.');
define('TEAM_ABSENCE_MARKER', 'Absent');
define('TEAM_ABSENCE_PAGE', 'Team page');
define('TEAM_ABSENCE_PEOPLE', 'Team member pages');
define('TEAM_ABSENCE_PEOPLE_PREVIOUS', 'Previous people');
define('TEAM_ABSENCE_PEOPLE_NEXT', 'More people');
define('TEAM_ABSENCE_UNAVAILABLE', 'Unavailable. Please reload.');

// Own stored open-row facts only, never an inferred rule violation.
define('OWN_OPEN_REVIEW_TITLE', 'Stored open entries');
define('OWN_OPEN_REVIEW_COUNT', '%d own entries without a stored end: start before %s (Europe/Berlin), or unreadable.');
define('OWN_OPEN_REVIEW_NOTE', 'A normal overnight shift may appear here. This does not prove forgotten work or a rule violation.');
define('OWN_OPEN_REVIEW_ACTION', 'Finish ongoing work normally. Request a reasoned correction or cancellation only if clarification is actually needed; another authorized person decides. No time is added automatically.');
define('OWN_OPEN_REVIEW_SNAPSHOT', 'Page-load snapshot; reload after bookings:');
define('OWN_OPEN_REVIEW_UNKNOWN_START', 'Unreadable start time');
define('OWN_OPEN_REVIEW_LINK', 'View correction / cancellation');
define('OWN_OPEN_REVIEW_LIMIT', 'At most five entries: unreadable starts first, then oldest starts. More of your own entries are in the time-record list.');
define('OWN_OPEN_REVIEW_REFRESH', 'Reload stored facts');
define('OWN_OPEN_REVIEW_UNAVAILABLE', 'Your open-entry list is currently unavailable. Please reload.');

// Fixed representation-only calendar states; never reflect runtime exceptions.
define('DASHBOARD_CALENDAR_LOADING', 'Preparing the calendar view …');
define('DASHBOARD_CALENDAR_UNAVAILABLE', 'Calendar view currently unavailable. Reload the page or open your own bookings.');
define('DASHBOARD_CALENDAR_NO_SCRIPT', 'The calendar view requires JavaScript. The existing attendance page remains available.');
define('DASHBOARD_CALENDAR_FALLBACK', 'Open your own bookings');
// Existing own-CSV date range: presentation only; export contract unchanged.
define('CSV_PERIOD_TITLE', 'Own CSV export for a date range');
define('CSV_PERIOD_FROM', 'From (Berlin start date)');
define('CSV_PERIOD_TO', 'To (inclusive)');
define('CSV_PERIOD_SUBMIT', 'Export range as CSV');
define('CSV_PERIOD_NOTE', 'Only your own entries by their Berlin start date, regardless of search or list page. Overnight shifts remain entirely on their start date. The quality check still covers all your entries; errors or unresolved overlaps outside the range can also block the download. Not a payroll-software format.');

define('CSV_EXPORT_NEW_CONTEXT_HINT', 'CSV downloads and error messages open separately; your selection stays on this page.');

define('HOLIDAY_INVENTORY_YEARS_TITLE', 'Find stored years');
define('HOLIDAY_INVENTORY_YEARS_NOTE', 'Recognized year prefixes only, up to the 20 newest. Row counts attest neither valid dates nor completeness, region or source; other malformed year prefixes are omitted.');
define('HOLIDAY_INVENTORY_YEARS_LIMITED', 'Further stored years are not listed; they can still be checked using the year field.');
define('HOLIDAY_INVENTORY_YEARS_EMPTY', 'No recognized stored years. This means neither no holidays nor an error-free inventory.');
define('HOLIDAY_INVENTORY_YEAR_ROWS', '%d · %d stored rows');
define('HOLIDAY_INVENTORY_UNASSIGNED_ROWS', '%d stored rows without a recognized year prefix from 0001 to 9999 are missing from year selection. Ask your administrator to review the original values; nothing was assigned or repaired.');

define('CORRECTION_REQUESTED_AT', 'Request submitted (Europe/Berlin)');
define('CORRECTION_DECIDED_AT', 'Decision stored');
define('CORRECTION_WITHDRAWN_AT', 'Withdrawal stored');
define('CORRECTION_TIME_UNAVAILABLE', 'Time unavailable');
define('SETTINGS_SAVE_SUCCESS_TITLE', 'Success!');
define('SETTINGS_SAVE_SUCCESS_MESSAGE', 'Settings updated successfully!');
define('LATEST_TIMESTAMP_REVIEW_HINT', 'Legacy-time review: At least one stored timestamp has an existing format, validity or Berlin daylight-saving finding. Display and calculation are unchanged and do not confirm an unambiguous legacy time. Review the original.');
?>
