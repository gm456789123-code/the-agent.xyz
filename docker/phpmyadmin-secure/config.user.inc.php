<?php
// Never automatically authenticate visitors with the application's DB account.
$cfg['Servers'][$i]['auth_type'] = 'cookie';
unset($cfg['Servers'][$i]['user'], $cfg['Servers'][$i]['password']);
$cfg['Servers'][$i]['AllowNoPassword'] = false;
