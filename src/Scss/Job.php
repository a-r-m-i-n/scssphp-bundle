<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\Scss;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;

class Job
{
    /**
     * @var string
     */
    private $assetName;

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var string absolute path to project directory (given from Kernel)
     */
    private $projectDir;

    /**
     * @var string
     */
    private $sourceFilePath;

    /**
     * @var string
     */
    private $destinationPath;


    public function __construct(string $assetName, array $configuration, string $projectDir)
    {
        $this->assetName = $assetName;
        $this->configuration = $configuration;
        $this->projectDir = $projectDir;

        $this->sourceFilePath = $this->buildSourceFilePath();
        $this->destinationPath = $this->buildDestinationPath();
    }

    public function getSourceFilePath(): string
    {
        return $this->sourceFilePath;
    }

    public function getSourceFileName(): string
    {
        return basename($this->getSourceFilePath());
    }

    protected function buildSourceFilePath(): string
    {
        $src = $this->projectDir . '/' . $this->configuration['src'];
        if (!file_exists($src)) {
            throw new \InvalidArgumentException(
                'Given src "' . $src . '" for asset "' . $this->assetName . '" not found!!'
            );
        }
        return realpath($src);
    }

    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    protected function buildDestinationPath(): string
    {
        return $this->projectDir . '/' . ($this->configuration['outputFolder'] ?? '') . '/' . $this->assetName;
    }

    public function execute(): Result
    {
        $timeTrackingStart = microtime(true);

        $compiler = new Compiler();
        $compiler->setImportPaths($this->getImportPaths());
        $compiler->setVariables($this->configuration['variables'] ?? []);
        $compiler->setFormatter($this->configuration['formatter'] ?? Crunched::class);
        // TODO: Add sourceMap options


        $result = new Result($this);

        try {
            $css = $compiler->compile(
                '@import "' . basename($this->sourceFilePath) . '";',
                dirname($this->sourceFilePath)
            );
        } catch (\Exception $exception) {
            return $result->markAsFailed($exception, microtime(true) - $timeTrackingStart);
        }

        if (isset($css) && !empty($css)) {
            $this->writeResultToFile($css);
        }

        return $result->markAsSuccessful(
            $compiler,
            microtime(true) - $timeTrackingStart
        );
    }

    private function getImportPaths(): array
    {
        $importPaths = [];
        foreach ($this->configuration['importPaths'] ?? [] as $importPath) {
            $realImportPath = realpath($this->projectDir . '/' . $importPath);
            if ($realImportPath) {
                $importPaths[] = $realImportPath;
            }
        }
        return array_merge(array_merge($importPaths ?? [], [dirname($this->sourceFilePath)]));
    }

    private function writeResultToFile(string $result): void
    {
        if (!is_dir(dirname($this->destinationPath))) {
            if (!mkdir($dir = dirname($this->destinationPath), 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException('Directory "' . $dir . '" was not created');
            }
        }
        if (!is_writable(dirname($this->destinationPath))) {
            throw new \RuntimeException('Can\'t write to "' . dirname($this->destinationPath) . '"!');
        }

        $status = file_put_contents($this->destinationPath, $result);
        if (!$status || !file_exists($this->destinationPath)) {
            throw new \RuntimeException('Error while writing to "' . $this->destinationPath . '".');
        }
    }

    public function getAssetName(): string
    {
        return $this->assetName;
    }

    public function getSanitizedAssetName(): string
    {
        return Parser::sanitizeAssetName($this->getAssetName());
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
