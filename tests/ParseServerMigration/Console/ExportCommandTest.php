<?php

namespace ParseServerMigration\Console\Command;

use Monolog\Logger;
use Parse\ParseClient;
use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseQuery;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

class ExportCommandTest extends TestCase
{
    /**
     * @var PictureRepository | ObjectProphecy
     */
    private $pictureRepository;

    /**
     * @var Logger
     */
    private $logger;

    protected function setUp()
    {
        ParseClient::initialize(Config::APP_ID, Config::REST_KEY, Config::MASTER_KEY);
        ParseClient::setServerURL(Config::PARSE_URL,'parse');

        $this->pictureRepository = $this->prophesize('ParseServerMigration\Console\PictureRepository');
        $this->logger = new NullLogger();
    }

    public function testExecuteForCommandAlias()
    {
        $command = new ExportCommand($this->pictureRepository->reveal(), $this->logger);
        $application = new Application();
        $command->setApplication($application);
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(), array('interactive' => false));

        /** @var QuestionHelper | ObjectProphecy $dialog */
        $dialog = $this->prophesize('Symfony\Component\Console\Helper\QuestionHelper');
        $dialog->getName()->willReturn('question');
        $dialog->getInputStream()->willReturn();

        $dialog->setHelperSet(Argument::type('Symfony\Component\Console\Helper\HelperSet'))->willReturn();

        $dialog->ask($commandTester->getInput(), $commandTester->getOutput(), Argument::type('Symfony\Component\Console\Question\Question'))->willReturn(true);

        // We override the standard helper with our mock
        $command->getHelperSet()->set($dialog->reveal(), 'question');

        $this->assertFalse($commandTester->getInput()->hasParameterOption(array('--no-interaction', '-n')));

        $inputStream = $application->getHelperSet()->get('question')->getInputStream();

//        $this->assertContains('list [options] [--] [<namespace>]', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
//        $this->assertContains('format=FORMAT', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
//        $this->assertContains('raw', $commandTester->getDisplay(), '->execute() returns a text help for the given command alias');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Parse migration tool');
        $io->section('Export command');
        $io->text('Upload existing parse pictures to S3 bucket and rename file in mongoDB');

        $number = $io->ask('Number of picture to export', 1, null);

        //Because we have 2 steps for each picture
        $io->progressStart($number *2);
        $io->newLine();

        $query = new ParseQuery(Config::PICTURES_TABLE_NAME);
        $query->limit($number);

        $results = $query->find();

        foreach ($results as $picture) {
            try {
                $io->text('Uploading picture');
                $resultUpload = $this->pictureRepository->uploadPicture($picture);
                $io->progressAdvance(1);
                $io->newLine();

                $io->text('Update picture file name');
                $resultRename = $this->pictureRepository->renamePicture($picture);
                $io->progressAdvance(1);

                $message = 'Export success for: ['.$picture->get('image')->getName().'] Uploaded to : ['.$resultUpload['ObjectURL'].']';
                $this->logger->info($message);
                $io->success($message);
            } catch (\ErrorException $exception) {
                $message = 'Upload failed for: [' .$picture->get('image')->getName().'] \nDetail error : [' .$exception->getMessage().']';

                $this->logger->error($message);
                $io->warning($message);
            }
        }
    }
}
