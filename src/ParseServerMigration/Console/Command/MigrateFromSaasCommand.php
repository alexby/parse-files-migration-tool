<?php

namespace ParseServerMigration\Console\Command;

use Monolog\Logger;
use Parse\ParseObject;
use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureApplicationService;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseQuery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class MigrateFromSaasCommand extends Command
{
    /**
     * @var PictureApplicationService
     */
    private $pictureApplicationService;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param PictureApplicationService $pictureApplicationService
     * @param Logger                    $logger
     */
    public function __construct(PictureApplicationService $pictureApplicationService, Logger $logger)
    {
        $this->pictureApplicationService = $pictureApplicationService;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('parse:migration:migrate')
            ->setDescription('Fetch existing SAAS Parse server and insert all data into MongoDB')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $query = new ParseQuery(Config::PARSE_FILES_CLASS_NAME);

        $io->progressStart($query->count() * 2);

        //This is crap but we can't count other wise
        $query = new ParseQuery(Config::PARSE_FILES_CLASS_NAME);

        try {
            $this->pictureApplicationService->migrateAllPictures();
        } catch (\ErrorException $exception) {
        }

        //Todo we need to compare perf between this way of dumping all images vs PictureRepository::migrateAllPictures()
        $query->each(function (ParseObject $picture) use ($io) {
            try {
                $io->text('Migrating picture\'s image');
                $message = $this->pictureApplicationService->migrateImage($picture);
                $io->progressAdvance(1);
                $io->newLine();
                $this->logger->info($message);
                $io->success($message);

                $io->text('Migrate picture\'s thumbnail');
                $message = $this->pictureApplicationService->migrateThumbnail($picture);
                $io->progressAdvance(1);
                $this->logger->info($message);
                $io->success($message);
            } catch (\ErrorException $exception) {
                $message = 'Upload failed for: ['.$picture->get('image')->getName().'] \nDetail error : ['.$exception->getMessage().']';

                $this->logger->error($message);
                $io->warning($message);
            }
        });
    }
}
