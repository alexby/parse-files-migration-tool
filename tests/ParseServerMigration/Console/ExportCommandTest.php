<?php

namespace ParseServerMigration\Console\Command;

use Monolog\Logger;
use Parse\ParseClient;
use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureApplicationService;
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
     * @var PictureApplicationService | ObjectProphecy
     */
    private $pictureApplicationService;

    /**
     * @var Logger
     */
    private $logger;

    protected function setUp()
    {
        ParseClient::initialize(Config::PARSE_APP_ID, Config::PARSE_REST_KEY, Config::PARSE_MASTER_KEY);
        ParseClient::setServerURL(Config::PARSE_FS_URL,'parse');

        $this->pictureApplicationService = $this->prophesize('ParseServerMigration\Console\PictureApplicationService');
        $this->logger = new NullLogger();
    }

    public function testExecuteForCommandAlias()
    {
        $this->pictureApplicationService->retrievePictures(1, false)->willReturn([]);

        $command = new ExportCommand($this->pictureApplicationService->reveal(), $this->logger);
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
        $this->assertContains('Export command', $commandTester->getDisplay());
    }
}
