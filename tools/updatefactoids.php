<?php

use \PDO;

$_DATABASE = array(
    'host' => 'localhost',
    'db' => 'panel',
    'user' => 'root',
    'pass' => getopt("p:")["p"]
);

$database = new PDO("mysql:host=" . $_DATABASE['host'] . ";dbname=" . $_DATABASE['db'], $_DATABASE['user'], $_DATABASE['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));

$factoids = file_get_contents("http://home.ghoti.me/~faqbot/faqdatabase");

$list = explode("\n", $factoids);

foreach ($list as $line) {
    if (!trim($line)) {
        continue;
    }
    echo "Importing: " . $line . "\n";
    list($name, $context) = explode('|', $line, 2);
    $set = $database->prepare("SELECT game FROM factoids WHERE name = ?");
    $set->execute(array($name));
    $record = $set->fetch();
    if (isset($record['game']) && $record['game'] == 0) {
        $database->prepare("UPDATE factoids SET content = ? WHERE game = 0 AND name = ?")
              ->execute(array($context, $name));
    } else {
        $database->prepare("INSERT INTO factoids (name, game, content) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE content = ?")
              ->execute(array($name, $context, $context));
    }
}