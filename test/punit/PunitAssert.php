<?php
class PunitAssert {

    public static function assertEquals($var1, $var2) { if ($var1 != $var2) self::fail(); }

    public static function assertStrictEquals($var1, $var2) { if ($var1 !== $var2) self::fail(); }

    public static function assertInstanceof($obj, $class) { if (!($obj instanceof $class)) self::fail(); }

    private static function fail() { throw new Exception("Assert fail."); }

}