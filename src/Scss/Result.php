<?php
namespace Armin\ScssphpBundle\Scss;

use ScssPhp\ScssPhp\Compiler;

class Result
{
    /**
     * @var Job
     */
    private $job;

    /**
     * @var bool
     */
    private $successful = false;

    /**
     * @var \DateTime
     */
    private $executedAt;

    /**
     * @var float
     */
    private $duration;

    /**
     * @var string
     */
    private $errorMessage;

    /**
     * @var array
     */
    private $parsedFiles = [];

    /**
     * @var array
     */
    private $compilerOptions = [];

    /**
     * @var bool
     */
    private $assetExistedBefore;

    public function __construct(Job $job)
    {
        $this->job = $job;
        $this->assetExistedBefore = file_exists($job->getDestinationPath());
        $this->executedAt = new \DateTime();
    }

    public function markAsSuccessful(Compiler $compiler, float $duration): self
    {
        $this->successful = true;
        $this->duration = $duration;
        $this->parsedFiles = $compiler->getParsedFiles();
        $this->compilerOptions = $compiler->getCompileOptions();
        return $this;
    }

    public function markAsFailed(\Exception $exception, float $duration): self
    {
        $this->successful = false;
        $this->duration = $duration;
        $messageLines = explode("\n", $exception->getMessage());
        $this->errorMessage = reset($messageLines); // Get first line from exception message
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getExecutedAt() : \DateTime
    {
        return $this->executedAt;
    }

    public function getParsedFiles(): array
    {
        return $this->parsedFiles;
    }

    public function getCompilerOptions(): array
    {
        return $this->compilerOptions;
    }

    public function getJob(): Job
    {
        return $this->job;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function hasAssetExistedBefore(): bool
    {
        return $this->assetExistedBefore;
    }
}
