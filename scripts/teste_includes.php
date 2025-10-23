<?php
// teste_includes.php
echo "Testando includes...<br>";

include 'db_connect.php';
echo "db_connect incluído<br>";

include_once 'game_logic.php';
echo "game_logic incluído<br>";

include_once 'daily_quests_functions.php'; 
echo "daily_quests incluído<br>";

echo "✅ Todos includes funcionaram!";
?>