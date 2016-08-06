<?php
use Mockery as m;

class XpressengineInstallerTest extends PHPUnit_Framework_TestCase
{
    public function testGetPackageBasePathReturnsBasePathString()
    {
        $installer = $this->getInstaller();

        $package = m::mock('Composer\Package\PackageInterface');
        $package->shouldReceive('getPrettyName')->andReturn('xe-foo/bar');

        $path = $installer->getPackageBasePath($package);

        $this->assertEquals('plugins/bar', $path);
    }

    public function testSupportsReturnsTrueWhenRightPackageType()
    {
        $installer = $this->getInstaller();

        $this->assertFalse($installer->supports('foobar-plugin'));
        $this->assertTrue($installer->supports('xpressengine-plugin'));
    }

    private function getInstaller()
    {
        $composer = m::mock('Composer\Composer');
        $io = m::mock('Composer\IO\IOInterface');
        $config = m::mock('Composer\Config');

        $composer->shouldReceive('getDownloadManager')->andReturnNull();
        $composer->shouldReceive('getConfig')->andReturn($config);

        $config->shouldReceive('get')->once()->with('vendor-dir')->andReturn('vendor');
        $config->shouldReceive('get')->once()->with('bin-dir')->andReturn('vendor/bin');

        return new Xpressengine\Installer\XpressengineInstaller($io, $composer);
    }
}
