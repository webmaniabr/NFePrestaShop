<?php
if (!defined('_PS_VERSION_')) { exit; }

/** Durable, per-order dispatch reservations; safe on the legacy database engines too. */
class WebmaniaNFeEmission
{
    private static $ready = false;

    public static function ensureTable()
    {
        if (self::$ready) { return true; }
        $engine = defined('_MYSQL_ENGINE_') && in_array(_MYSQL_ENGINE_, array('InnoDB', 'MyISAM'), true) ? _MYSQL_ENGINE_ : 'InnoDB';
        self::$ready = (bool) Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'webmaniabrnfe_emissions` (
            `id_order` INT UNSIGNED NOT NULL,
            `token` CHAR(64) NOT NULL,
            `phase` VARCHAR(16) NOT NULL,
            `requested_at` DATETIME NOT NULL,
            `uuid` VARCHAR(64) NOT NULL DEFAULT \'\',
            PRIMARY KEY (`id_order`)
        ) ENGINE='.$engine.' DEFAULT CHARSET=utf8');
        return self::$ready;
    }

    public static function acquire($orderId)
    {
        $orderId = (int) $orderId;
        if ($orderId < 1 || !self::ensureTable()) { return false; }
        $db = Db::getInstance();
        // Only an expired preparation can be reclaimed. Dispatched/uncertain rows never expire.
        if (!$db->execute('DELETE FROM `'._DB_PREFIX_.'webmaniabrnfe_emissions` WHERE id_order = '.$orderId.
            " AND phase = 'reserved' AND requested_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)")) { return false; }
        $token = WebmaniaNFeSecurity::randomToken();
        if (!$db->execute('INSERT IGNORE INTO `'._DB_PREFIX_.'webmaniabrnfe_emissions` (id_order, token, phase, requested_at) VALUES ('.
            $orderId.", '".pSQL($token)."', 'reserved', UTC_TIMESTAMP())")) { return false; }
        $row = self::find($orderId, $token);
        return $row && $row['phase'] === 'reserved' ? $token : false;
    }

    public static function find($orderId, $token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token) || !self::ensureTable()) { return false; }
        $row = self::current($orderId);
        return is_array($row) && isset($row['token']) && WebmaniaNFeSecurity::equals($row['token'], $token) ? $row : false;
    }

    public static function current($orderId)
    {
        if ((int) $orderId < 1 || !self::ensureTable()) { return false; }
        return Db::getInstance()->getRow('SELECT token, phase, requested_at, uuid FROM `'._DB_PREFIX_.'webmaniabrnfe_emissions` WHERE id_order = '.(int) $orderId, false);
    }

    public static function dispatch($orderId, $token)
    {
        if (!self::find($orderId, $token)) { return false; }
        if (!Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'webmaniabrnfe_emissions` SET phase = \'dispatching\', requested_at = UTC_TIMESTAMP() WHERE id_order = '.(int) $orderId.
            " AND token = '".pSQL($token)."' AND phase = 'reserved'")) { return false; }
        return (int) Db::getInstance()->Affected_Rows() === 1;
    }

    public static function remember($orderId, $token, $uuid)
    {
        if (!WebmaniaNFeSecurity::validUuid($uuid) || !self::find($orderId, $token)) { return false; }
        return Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'webmaniabrnfe_emissions` SET uuid = \''.pSQL($uuid).'\' WHERE id_order = '.(int) $orderId.
            " AND token = '".pSQL($token)."'");
    }

    public static function release($orderId, $token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { return false; }
        return Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'webmaniabrnfe_emissions` WHERE id_order = '.(int) $orderId.
            " AND token = '".pSQL($token)."'");
    }

    /** Explicit administrative recovery only; never reclaim an active or identified dispatch. */
    public static function releaseUnidentified($orderId, $token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { return false; }
        $db = Db::getInstance();
        if (!$db->execute('DELETE FROM `'._DB_PREFIX_.'webmaniabrnfe_emissions` WHERE id_order = '.(int) $orderId.
            " AND token = '".pSQL($token)."' AND phase = 'dispatching' AND uuid = ''".
            ' AND requested_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)')) { return false; }
        return (int) $db->Affected_Rows() === 1;
    }
}
