<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: service.proto

namespace GPBMetadata;

class Service
{
    public static $is_initialized = false;

    public static function initOnce()
    {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
            return;
        }
        $pool->internalAddGeneratedFile(hex2bin(
            '0a6e0a0d736572766963652e70726f746f12077365727669636522160a07' .
            '4d657373616765120b0a036d736718012001280932340a044563686f122c' .
            '0a0450696e6712102e736572766963652e4d6573736167651a102e736572' .
            '766963652e4d6573736167652200620670726f746f33'
        ));

        static::$is_initialized = true;
    }
}
