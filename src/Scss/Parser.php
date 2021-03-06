<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\Scss;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

class Parser
{
    private const CACHE_KEY_PREFIX = 'scssphp_';

    private $config;

    private $request;

    /**
     * @var AdapterInterface|FilesystemAdapter
     */
    private $cache;

    private $kernel;


    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        AdapterInterface $cache,
        KernelInterface $kernel
    ) {
        $this->config = $container->hasParameter('scssphp') ? $container->getParameter('scssphp') : [];
        $this->request = $requestStack->getCurrentRequest();
        $this->cache = $cache;
        $this->kernel = $kernel;
    }

    public function isConfigured(string $assetName): bool
    {
        return array_key_exists($assetName, $this->config['assets']);
    }

    public function makeJob(string $assetName): Job
    {
        return new Job($assetName, $this->config['assets'][$assetName], $this->kernel->getProjectDir());
    }

    public function parse(string $assetName, bool $force = false): string
    {
        $this->registerRequiredAsset($assetName);
        $assetConfig = $this->config['assets'][$assetName];

        $job = $this->makeJob($assetName);
        $resultCacheKey = self::makeCacheKey($assetName);
        $cacheItem = $this->cache->getItem($resultCacheKey);

        if ($force || !file_exists($job->getDestinationPath())) {
            if ($cacheItem->isHit()) {
                $cacheItem->set(null);
                $this->cache->save($cacheItem);
            }
        } else {
            // Check if there is a cached job (if autoUpdate is enabled)
            if ($this->config['autoUpdate'] && $cachedResult = $cacheItem->get()) {
                if ($this->checkForUpdates($cachedResult, $assetConfig)) {
                    $this->compileScss($job, $cacheItem);
                }
                return '/' . ltrim($assetName, '/');
            }
        }
        $this->compileScss($job, $cacheItem);
        return '/' . ltrim($assetName, '/');
    }

    private function checkForUpdates(Result $result, array $assetConfiguration)
    {
        if (!$result->isSuccessful()) {
            return true;
        }

        // Check parsed files
        foreach ($result->getParsedFiles() as $filePath => $lastModificationTimestamp) {
            if (!file_exists($filePath)) {
                return true;
            }
            if (filemtime($filePath) > $lastModificationTimestamp) {
                return true;
            }
        }

        // Check configuration
        return $result->getJob()->getConfiguration() !== $assetConfiguration;
    }

    private function registerRequiredAsset(string $path): void
    {
        if ($this->request) {
            $requiredAssets = $this->request->attributes->get('requiredAssets', []);
            if (!in_array($path, $requiredAssets, true)) {
                $requiredAssets[] = $path;
            }
            $this->request->attributes->set('requiredAssets', $requiredAssets);
        }
    }

    private function compileScss(Job $job, CacheItem $cacheItem): Result
    {
        $cacheItem->set($result = $job->execute());
        $this->cache->save($cacheItem);
        $this->registerBuiltAsset($job->getAssetName());
        return $result;
    }

    private function registerBuiltAsset(string $path): void
    {
        if ($this->request) {
            $builtAssets = $this->request->attributes->get('builtAssets', []);
            if (!in_array($path, $builtAssets, true)) {
                $builtAssets[] = $path;
            }
            $this->request->attributes->set('builtAssets', $builtAssets);
        }
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    public function getConfiguration(): array
    {
        return $this->config;
    }

    public function getResult(string $assetName): ?Result
    {
        $cacheItem = $this->cache->getItem(self::makeCacheKey($assetName));
        return $cacheItem->get();
    }

    public static function sanitizeAssetName(string $assetName): string
    {
        return preg_replace('/[^A-Z0-9]/i', '_', $assetName);
    }

    public static function makeCacheKey(string $assetName): string
    {
        return self::CACHE_KEY_PREFIX . self::sanitizeAssetName($assetName);
    }
}
