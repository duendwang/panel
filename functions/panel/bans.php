<?php

namespace AE97\Panel;

use \PDO,
    \PDOException;

class Bans {

    private static $database;
    private static $bansPerPage = 20;

    public static function getBan($id) {
        self::validateDatabase();
        try {
            $statement = self::$database->prepare("SELECT id, users.username, content, kickMessage, issueDate, expireDate, channel, type, notes "
                    . "FROM bans "
                    . "LEFT JOIN banchannels ON bans.id = banId "
                    . "LEFT JOIN users ON users.uuid = issuedBy "
                    . "WHERE id = ?");
            $statement->execute(array($id));
            $result = self::combineChans($statement->fetchAll());
            return count($result) >= 1 ? $result[$id] : null;
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return null;
        }
    }

    public static function getBans($page = 1) {
        if ($page == null) {
            $page = 1;
        }
        self::validateDatabase();
        try {
            $idList = self::$database->prepare("SELECT filter.id FROM bans AS filter WHERE filter.expireDate IS NULL OR filter.expireDate > current_timestamp() "
                    . "ORDER BY filter.issueDate DESC "
                    . "LIMIT " . strval(intval($page - 1) * self::$bansPerPage) . ", " . self::$bansPerPage);
            $idList->execute();
            $ids = $idList->fetchAll(PDO::FETCH_ASSOC);
            $idCasted = array();
            foreach ($ids as $id) {
                $idCasted[] = $id["id"];
            }
            $query = "SELECT id, users.username, content, kickMessage, issueDate, expireDate, channel, type, notes "
                    . "FROM bans "
                    . "LEFT JOIN banchannels ON bans.id = banId "
                    . "LEFT JOIN users ON users.uuid = issuedBy "
                    . "WHERE id IN (" . implode(',', $idCasted)
                    . ") ORDER BY issueDate DESC";

            $statement = self::$database->prepare($query);
            $statement->execute();
            $record = $statement->fetchAll(PDO::FETCH_ASSOC);
            return self::combineChans($record);
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return array();
        }
    }

    public static function getBanPages() {
        self::validateDatabase();
        try {
            $statement = self::$database->prepare("SELECT count(*) AS count "
                    . "FROM bans "
                    . "WHERE expireDate IS NULL OR expireDate > current_timestamp()"
            );
            $statement->execute();
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            return ceil($record['count'] / self::$bansPerPage);
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return array();
        }
    }

    public static function addBan($mask, $issuer, $kickMessage, $daysToLast, $notes = "No private notes", $isExtended = false) {
        if (!preg_match('/[^\*\!\@]/', $mask)) {
            //string is just a complete wildcard ban, cannot allow
            //throw new \Exception("");
            return false;
        }
        self::validateDatabase();
        $convertedMask = str_replace("*", "%", $mask);

        if (empty(trim($notes))) {
            $notes = null;
        }
        
        $duration = $daysToLast <= 0 ? 3650 : $daysToLast;

        try {
            $statement = self::$database->prepare("INSERT INTO bans (type, content, issuedBy, kickMessage, notes, expireDate) VALUES (?,?,?,?,?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL " . $duration . " DAY))");
            $statement->execute(array($isExtended ? 1 : 0, $convertedMask, $issuer, $kickMessage, $notes));
            return self::$database->lastInsertId();
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return false;
        }
    }

    public static function removeBan($id) {
        self::validateDatabase();
        try {
            self::$database->prepare("DELETE FROM banchannels WHERE banId = ?")->execute(array($id));
            self::$database->prepare("DELETE FROM bans WHERE id = ?")->execute(array($id));
            return true;
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return false;
        }
    }

    public static function addChannelToBan($banId, $channel) {
        self::validateDatabase();
        try {
            self::$database->prepare("INSERT INTO banchannels VALUES(?,?)")->execute(array($banId, $channel));
            return true;
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return false;
        }
    }

    public static function removeChannelFromBan($banId, $channel) {
        self::validateDatabase();
        try {
            self::$database->prepare("DELETE FROM banchannels WHERE banId = ? AND channel = ?")->execute(array($banId, $channel));
            return true;
        } catch (PDOException $ex) {
            Utilities::logError($ex);
            return false;
        }
    }

    public static function expire($banId) {
        self::validateDatabase();
        try {
            self::$database->prepare('UPDATE bans SET expireDate = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute(array($banId));
            return true;
        } catch (Exception $ex) {
            Utilities::logError($ex);
            return false;
        }
    }

    private static function validateDatabase() {
        if (self::$database == null) {
            $_DATABASE = Config::getGlobal('database');
            self::$database = new PDO("mysql:host=" . $_DATABASE['host'] . ";dbname=" . $_DATABASE['db'], $_DATABASE['user'], $_DATABASE['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'"));
            self::$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);            
        }
    }

    private static function combineChans($record) {
        $casted = array();
        foreach ($record as $ban) {
            if (!isset($casted[$ban['id']])) {
                $casted[$ban['id']] = array(
                    'id' => $ban['id'],
                    'issuer' => $ban['username'],
                    'kickmessage' => $ban['kickMessage'],
                    'issueDate' => $ban['issueDate'],
                    'expireDate' => $ban['expireDate'],
                    'type' => $ban['type'] === 0 ? "standard" : "extended",
                    'channels' => array($ban['channel']),
                    'content' => str_replace("%", "*", $ban['content']),
                    'notes' => $ban['notes']
                );
            } else {
                $casted[$ban['id']]['channels'][] = $ban['channel'];
            }
        }
        return $casted;
    }

}
