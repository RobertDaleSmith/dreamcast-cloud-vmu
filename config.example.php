<?php
// Copy to config.php (gitignored) to require a password for deleting saves.
// Generate the hash with:
//   php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
define('CLOUDVMU_ADMIN_PASSWORD_HASH', '');
