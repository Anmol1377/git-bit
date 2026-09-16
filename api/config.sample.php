<?php
/* Copy this to config.php, fill it in, and upload ONLY config.php to your host.
   config.php is gitignored so the credentials never reach the public repo.

   quiz.php reads this. The standalone admin page (-prvite.php) does not — it
   carries its own copy of these values, on purpose. */
return [
  'host' => 'YOUR_MYSQL_HOST',        // e.g. sqlNNN.infinityfree.com
  'port' => 3306,
  'user' => 'YOUR_MYSQL_USER',        // e.g. if0_00000000
  'pass' => 'YOUR_MYSQL_PASSWORD',
  'name' => 'YOUR_DATABASE_NAME',     // e.g. if0_00000000_something

  // Any string you like. The dashboard asks for it before starting a new
  // session, so a random player cannot wipe the board mid-talk.
  'host_key' => 'change-me',
];
