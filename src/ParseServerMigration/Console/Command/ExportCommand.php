<?php

namespace ParseServerMigration\Console\Command;

use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package ParseServerMigration\Console\Command
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class ExportCommand extends Command
{
    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PictureRepository $pictureRepository
     * @param LoggerInterface $logger
     */
    public function __construct(PictureRepository $pictureRepository, LoggerInterface $logger)
    {
        $this->pictureRepository = $pictureRepository;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('parse:migration:export')
            ->setDescription('Upload existing parse pictures to S3 bucket and rename')
        ;
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
