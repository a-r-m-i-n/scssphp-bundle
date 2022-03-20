<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\Scss;

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\ParserException;
use ScssPhp\ScssPhp\Exception\SassException;

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
     * @var array|null
     */
    private $errorSourcePosition = null;

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

    /**
     * @var int
     */
    private $compiledSize = 0;

    public function __construct(Job $job)
    {
        $this->job = $job;
        $this->assetExistedBefore = file_exists($job->getDestinationPath());
        $this->executedAt = new \DateTime();
    }

    public function markAsSuccessful(Compiler $compiler, float $duration, int $size): self
    {
        $this->successful = true;
        $this->duration = $duration;
        $this->compiledSize = $size;
        $this->parsedFiles = $compiler->getParsedFiles();
        $this->compilerOptions = $compiler->getCompileOptions();
        return $this;
    }

    public function markAsFailed(SassException $exception, float $duration): self
    {
        $this->successful = false;
        $this->duration = $duration;
        $messageLines = explode("\n", $exception->getMessage());
        $this->errorMessage = reset($messageLines); // Get first line from exception message
        if ($exception instanceof ParserException) {
            $this->errorSourcePosition = $exception->getSourcePosition();
        }
        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getExecutedAt(): \DateTime
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
        $sourcePosition = $this->getErrorSourcePosition() !== null ? ' (' . implode(', ', $this->getErrorSourcePosition()) . ')' : '';

        return $this->errorMessage . $sourcePosition;
    }

    public function getErrorSourcePosition(): ?array
    {
        return $this->errorSourcePosition;
    }

    public function hasAssetExistedBefore(): bool
    {
        return $this->assetExistedBefore;
    }

    public function getCompiledSize() : int
    {
        return $this->compiledSize;
    }
}
