<?php

namespace humhub\modules\updater\jobs;

use humhub\modules\file\libs\FileHelper;
use humhub\modules\queue\ActiveJob;
use Yii;
use yii\base\ErrorException;

class CleanupJob extends ActiveJob
{
    public $backupKeepTime = 60 * 60 * 24 * 2;

    /**
     * @inheritdoc
     */
    public function run()
    {
        $this->cleanupBackups();
    }

    private function cleanupBackups()
    {
        $backupFolder = Yii::getAlias('@runtime/updater/backups');

        if (!is_dir($backupFolder)) {
            return;
        }

        foreach (scandir($backupFolder) as $backup) {
            if (preg_match('/.*_(\d{8,})$/', $backup, $matches) && isset($matches[1])) {
                $backupDate = $matches[1];
                if ($backupDate + $this->backupKeepTime < time()) {
                    try {
                        FileHelper::removeDirectory($backupFolder . DIRECTORY_SEPARATOR . $backup);
                    } catch (ErrorException $e) {
                        Yii::error("Could not delete outdated backup: " . $backupFolder, 'updater');
                    }
                }
            }
        }

    }

}
