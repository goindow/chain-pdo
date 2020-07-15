<?php
class PunitAssert {

    public static function assertEquals($var1, $var2) { if ($var1 != $var2) self::fail(__FUNCTION__); }

    public static function assertStrictEquals($var1, $var2) { if ($var1 !== $var2) self::fail(__FUNCTION__); }

    public static function assertInstanceof($object, $class) { if (!($object instanceof $class)) self::fail(__FUNCTION__); }

    public static function assertInt($var) { if (!is_int($var)) self::fail(__FUNCTION__); }

    public static function assertString($var) { if (!is_string($var)) self::fail(__FUNCTION__); }

    public static function assertArray($var) { if (!is_array($var)) self::fail(__FUNCTION__); }

    public static function assertAssocArray($var) { if (!self::is_assoc($var)) self::fail(__FUNCTION__); }

    public static function assertNormalArray($var) { if (self::is_assoc($var)) self::fail(__FUNCTION__); }

    public static function assertGt($var1, $var2) { if (!($var1 > $var2)) self::fail(__FUNCTION__); }

    private static function is_assoc($array) { return array_diff_assoc(array_keys($array), range(0, count($array) - 1)) ? true : false; }

    private static function fail($functionName) { throw new Exception("${functionName} fail."); }

}