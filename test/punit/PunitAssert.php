<?php
class PunitAssert {

    public static function assertEquals($var1, $var2) { if ($var1 != $var2) self::fail(__FUNCTION__); }

    public static function assertStrictEquals($var1, $var2) { if ($var1 !== $var2) self::fail(__FUNCTION__); }

    public static function assertInstanceof($obj, $class) { if (!($obj instanceof $class)) self::fail(__FUNCTION__); }

    public static function assertString($var) { if (!is_string($var)) self::fail(__FUNCTION__); }

    public static function assertInt($var) { if (!is_int($var)) self::fail(__FUNCTION__); }

    public static function assertGt($var1, $var2) { if (!($var1 > $var2)) self::fail(__FUNCTION__); }

    private static function fail($funcName) { throw new Exception("${funcName} fail."); }

}