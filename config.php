<?php
/*
 * RizQuiz configuration
 * ---------------------
 * Admin accounts, questions, events, players and scores are stored in data/rizquiz.sqlite
 * and managed in the app. The very first visit to login.php creates the master admin.
 */

define('APP_NAME', 'RizQuiz');

// The master admin account is permanently linked to this phone number.
// It is also required (as a confirmation) when the master account is first created.
define('MASTER_PHONE', '7148877971');

// Lost the master's authenticator AND backup codes? Set this to true, log in with the master
// password to set up a new authenticator, then set it back to false.
define('MASTER_RESET_2FA', false);

// Where the SQLite database file lives. The folder is created automatically.
define('DB_PATH', __DIR__ . '/data/rizquiz.sqlite');

date_default_timezone_set('America/Los_Angeles');
