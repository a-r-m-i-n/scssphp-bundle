<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\DataCollector;

use Armin\ScssphpBundle\Scss\Parser;
use Armin\ScssphpBundle\Scss\Result;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\KernelInterface;

class ScssphpDataCollector extends DataCollector
{
    private $config;

    private $cache;

    private $kernel;


    public function __construct(ContainerInterface $container, FilesystemAdapter $cache, KernelInterface $kernel)
    {
        $this->config = $container->hasParameter('scssphp') ? $container->getParameter('scssphp') : [];
        $this->cache = $cache;
        $this->kernel = $kernel;
        $this->reset();
    }

    public function reset()
    {
        $this->data = [
            'config' => [],
            'results' => [],
            'requiredAssets' => [],
            'buildAssets' => [],
            'scssphpVersion' => ''
        ];
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        // Collect recent build from cache
        $cacheItems = [];
        foreach (array_keys($this->config['assets']) as $assetName) {
            $key = Parser::makeCacheKey($assetName);
            $cacheItem = $this->cache->getItem($key);
            $result = $cacheItem->get();
            if ($result) {
                $cacheItems[$key] = $cacheItem->get();
            }
        }

        // Collect required assets
        $builtAssets = $request->attributes->get('builtAssets', []);
        $requiredAssets = [];
        foreach ($request->attributes->get('requiredAssets', []) as $assetName) {
            $key = Parser::makeCacheKey($assetName);
            $result = $cacheItems[$key];
            if ($result) {
                $requiredAssets[$assetName] = [
                    'existed' => $result->hasAssetExistedBefore(),
                    'built' => in_array($assetName, $builtAssets, true)
                ];
            }
        }

        // Collect composer.json (for version number)
        $bundlePath = $this->kernel->locateResource('@ScssphpBundle');
        $composerJson = file_get_contents($bundlePath . '/../composer.json');
        $composer = json_decode($composerJson, true);

        $this->data = [
            'config' => $this->config,
            'results' => $cacheItems,
            'requiredAssets' => $requiredAssets,
            'builtAssets' => $builtAssets,
            'scssphpVersion' => $composer['version']
        ];
    }

    public function getName()
    {
        return 'scssphp.collector';
    }


    public function getRequest()
    {
        return $this->data['request'];
    }

    public function getConfig(): array
    {
        return $this->data['config'] ?? [];
    }

    public function hasConfig(): bool
    {
        return !empty($this->data['config']);
    }

    /**
     * @return Result[]
     */
    public function getResults(): array
    {
        return $this->data['results'] ?? [];
    }

    public function getResultsWithErrors(): array
    {
        $resultsWithErrors = [];
        foreach ($this->getResults() as $result) {
            if ($result && !$result->isSuccessful()) {
                $resultsWithErrors[] = $result;
            }
        }
        return $resultsWithErrors;
    }

    public function hasErrors(): bool
    {
        foreach ($this->getResults() as $result) {
            if ($result && !$result->isSuccessful()) {
                return true;
            }
        }
        return false;
    }

    public function getRequiredAssets(): array
    {
        return $this->data['requiredAssets'] ?? [];
    }

    public function getBuiltAssets(): array
    {
        return $this->data['builtAssets'] ?? [];
    }

    public function getScssphpVersion(): string
    {
        return $this->data['scssphpVersion'];
    }
}
