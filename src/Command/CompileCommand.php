<?php declare(strict_types=1);
namespace Armin\ScssphpBundle\Command;

use Armin\ScssphpBundle\Scss\Parser;
use Armin\ScssphpBundle\Scss\Result;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompileCommand extends Command
{
    protected static $defaultName = 'scssphp:compile';

    private $choice = '';

    private $scssParser;

    public function __construct(string $name = null, Parser $scssParser = null)
    {
        parent::__construct($name);
        $this->scssParser = $scssParser;
    }

    protected function configure()
    {
        $this
            ->setDescription('Compiles configured SCSS sources.')
            ->addArgument(
                'asset',
                InputArgument::OPTIONAL,
                'Name or number of asset to compile. Use "all" to re-compile all configured assets.'
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->scssParser->getConfiguration();

        $this->choice = $input->getArgument('asset') ?? 'all';
        if (count($config['assets']) > 1) {
            $choices = ['all'];
            foreach (array_keys($config['assets']) as $assetName) {
                $choices[] = $assetName;
            }

            // On invalid input
            $forceAsking = false;
            if (is_numeric($this->choice)) {
                $this->choice = (int) $this->choice;
                if (!isset($choices[$this->choice])) {
                    $forceAsking = true;
                    $this->choice = 'all';
                } else {
                    $this->choice = $choices[$this->choice];
                }
            } elseif (!in_array($this->choice, $choices, true)) {
                $forceAsking = true;
                $this->choice = 'all';
            }

            if ($forceAsking || (!$input->getArgument('asset') && $input->getArgument('asset') !== '0')) {
                $this->choice = $io->choice(
                    'There are several assets configured. Which one do you want to compile?',
                    $choices,
                    $this->choice
                );
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->getFormatter()->setStyle('success', new OutputFormatterStyle('green', null, ['bold']));
        $config = $this->scssParser->getConfiguration();

        $error = false;
        if ($this->choice !== 'all') {
            $add = file_exists($this->scssParser->makeJob($this->choice)->getDestinationPath()) ? ' (and overwrite!)' : '';
            $io->write('Compiling' . $add . ' "<comment>' . $this->choice . '</comment>"... ');
            $result = $this->parse($this->choice, $io);
            $error = !$result || !$result->isSuccessful();
        } else {
            $io->writeln('Start with compiling all ' . count($config['assets']) . ' SCSS sources...');
            foreach (array_keys($config['assets']) as $assetName) {
                $add = file_exists($this->scssParser->makeJob($assetName)->getDestinationPath())
                        ? ' (and overwrite!)' : '';
                $io->write(' -> compiling' . $add . ' "<comment>' . $assetName . '</comment>"... ');
                $result = $this->parse($assetName, $io);
                if (!$result || !$result->isSuccessful()) {
                    $error = true;
                }
            }
        }
        $style = $error ? 'error' : 'success';
        $io->writeln('<'. $style . '>Finished SCSS compiling ' .
                    ($error ? 'with errors!' : 'successfully.') . '</' . $style . '>');
    }

    protected function parse(string $assetName, SymfonyStyle $io): ?Result
    {
        $this->scssParser->parse($assetName, true);
        if ($result = $this->scssParser->getResult($assetName)) {
            $io->write($result->isSuccessful() ? '<success>OK</success>' : '<error>ERROR</error>');
            $io->writeln(' (' . round($result->getDuration(), 3) . 's)');
            if (!$result->isSuccessful()) {
                $io->error('Error during compiling "' . $assetName . '"' . PHP_EOL . $result->getErrorMessage());
            }
        }
        return $result;
    }
}
