<?php
// Load plugin definitions in WordPress mu-plugin filename order without a DB.
// This smoke check intentionally lets PHP report duplicate declarations.
function add_action($hook, $callback) {}
function add_filter(...$args) {}
define('DAY_IN_SECONDS', 86400);
foreach (glob(__DIR__ . '/../docker/wp-content/mu-plugins/*.php') as $plugin) {
    require_once $plugin;
}
echo "Plugin definitions loaded successfully\n";
