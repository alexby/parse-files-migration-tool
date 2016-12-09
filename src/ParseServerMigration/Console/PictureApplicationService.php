<?php

namespace ParseServerMigration\Console;

use Parse\ParseObject;
use ParseServerMigration\Config;

class PictureApplicationService
{
    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @param PictureRepository $pictureRepository
     */
    public function __construct(PictureRepository $pictureRepository)
    {
        $this->pictureRepository = $pictureRepository;
    }

    /**
     * @param ParseObject $picture
     *
     * @return string
     */
    public function migrateImage(ParseObject $picture) : string
    {
        $originalFileName = $picture->get(Config::PARSE_FILES_FIELD_NAME)->getName();
        $updateResult = $this->pictureRepository->renameImage($picture);

        if ($updateResult->getMatchedCount() != 1) {
            return 'Image : ['.$originalFileName.'] already migrated continuing';
        }

        $uploadResult = $this->pictureRepository->uploadImage($picture);

        return 'Migration success for: ['.$originalFileName.'] Uploaded to : ['.$uploadResult['ObjectURL'].']';
    }

    /**
     * @param ParseObject $picture
     *
     * @return string
     */
    public function migrateThumbnail(ParseObject $picture)
    {
        if ($picture->get(Config::PARSE_FILES_THUMBNAIL_FIELD_NAME) === null) {
            return 'No thumbnail for photo';
        }

        $originalFileName = $picture->get(Config::PARSE_FILES_THUMBNAIL_FIELD_NAME)->getName();
        $updateResult = $this->pictureRepository->renameThumbnail($picture);

        if ($updateResult->getMatchedCount() != 1) {
            return 'Thumbnail : ['.$originalFileName.'] already migrated continuing';
        }

        $uploadResult = $this->pictureRepository->uploadThumbnail($picture);

        return 'Migration success for: ['.$originalFileName.'] Uploaded to : ['.$uploadResult['ObjectURL'].']';
    }

    /**
     * @param int $limit
     * @param bool $orderDesc
     *
     * @return mixed
     */
    public function retrievePictures(int $limit, bool $orderDesc)
    {
        $images = $this->pictureRepository->findAllImages($limit, $orderDesc);

        return $images;
    }
    /**
     * This will actually read from Parse server and insert data into a given MongoDB database.
     *
     * @return \MongoDB\InsertManyResult
     *
     * @throws \Exception
     */
    public function migrateAllPictures()
    {
        $insertResult = $this->pictureRepository->migrateAllPictures();

        if ($insertResult->getInsertedCount()) {
            throw new \Exception('An error occurred when inserting document into mongoDB');
        }

        return $insertResult;
    }
}
