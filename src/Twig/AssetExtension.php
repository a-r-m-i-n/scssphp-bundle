<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\Twig;

use Armin\ScssphpBundle\Scss\Parser;
use Symfony\Component\Asset\Packages;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Adjusted copy of \Symfony\Bridge\Twig\Extension\AssetExtension
 */
final class AssetExtension extends AbstractExtension
{
    private $packages;

    public function __construct(Packages $packages, Parser $scssParser)
    {
        $this->packages = $packages;
        $this->scssParser = $scssParser;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', [$this, 'getAssetUrl']),
            new TwigFunction('asset_version', [$this, 'getAssetVersion']),
        ];
    }

    public function getAssetUrl($path, $packageName = null)
    {
        if ($this->scssParser->isEnabled() && $this->scssParser->isConfigured($path)) {
            $assetPath = $this->scssParser->parse($path);
            $result = $this->scssParser->getResult($path);
            if ($result && $result->getJob()->getConfiguration()['appendTimestamp'] &&
                file_exists($result->getJob()->getDestinationPath())
            ) {
                return $assetPath . '?' . filemtime($result->getJob()->getDestinationPath());
            }
            return $assetPath;
        }
        return $this->packages->getUrl($path, $packageName);
    }

    /**
     * Returns the version of an asset.
     */
    public function getAssetVersion(string $path, string $packageName = null): string
    {
        return $this->packages->getVersion($path, $packageName);
    }
}
