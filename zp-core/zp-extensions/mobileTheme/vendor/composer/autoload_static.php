<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitd5350eb3a139c07c29d30a628169cbde
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
        ),
        'D' => 
        array (
            'Detection\\' => 10,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Detection\\' => 
        array (
            0 => __DIR__ . '/..' . '/mobiledetect/mobiledetectlib/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitd5350eb3a139c07c29d30a628169cbde::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitd5350eb3a139c07c29d30a628169cbde::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitd5350eb3a139c07c29d30a628169cbde::$classMap;

        }, null, ClassLoader::class);
    }
}
